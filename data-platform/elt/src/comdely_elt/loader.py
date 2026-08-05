from __future__ import annotations

from datetime import datetime, timezone
from typing import Iterable
from uuid import UUID, uuid4

from sqlalchemy import Connection, Engine, delete, func, select, text, update
from sqlalchemy.dialects.postgresql import insert

from comdely_elt.schema import (
    LoadStrategy,
    TABLE_SPECS,
    TableSpec,
    etl_run,
    etl_watermark,
    metadata,
)


class TargetStore:
    def __init__(self, engine: Engine, source_system: str) -> None:
        self.engine = engine
        self.source_system = source_system

    def bootstrap(self) -> None:
        with self.engine.begin() as connection:
            for schema in ("raw", "staging", "analytics", "marts", "meta"):
                connection.execute(text(f'CREATE SCHEMA IF NOT EXISTS "{schema}"'))
            metadata.create_all(connection)

    def start_run(self, mode: str) -> UUID:
        run_id = uuid4()
        with self.engine.begin() as connection:
            connection.execute(
                etl_run.insert().values(
                    id=run_id,
                    mode=mode,
                    status="RUNNING",
                    source_system=self.source_system,
                    started_at=datetime.now(timezone.utc),
                )
            )
        return run_id

    def mark_failed(self, run_id: UUID, error: Exception) -> None:
        message = f"{type(error).__name__}: {error}"[:4000]
        with self.engine.begin() as connection:
            connection.execute(
                update(etl_run)
                .where(etl_run.c.id == run_id)
                .values(
                    status="FAILED",
                    finished_at=datetime.now(timezone.utc),
                    error_message=message,
                )
            )

    def mark_succeeded(
        self,
        connection: Connection,
        run_id: UUID,
        source_counts: dict[str, int],
        target_counts: dict[str, int],
        loaded_counts: dict[str, int],
    ) -> None:
        connection.execute(
            update(etl_run)
            .where(etl_run.c.id == run_id)
            .values(
                status="SUCCEEDED",
                finished_at=datetime.now(timezone.utc),
                source_counts=source_counts,
                target_counts=target_counts,
                loaded_counts=loaded_counts,
                error_message=None,
            )
        )

    @staticmethod
    def prepare_seen_ids(connection: Connection) -> None:
        connection.execute(
            text(
                "CREATE TEMPORARY TABLE IF NOT EXISTS etl_seen_ids ("
                "source_table text NOT NULL, source_id bigint NOT NULL, "
                "PRIMARY KEY (source_table, source_id)) ON COMMIT DROP"
            )
        )

    @staticmethod
    def clear_raw_tables(connection: Connection) -> None:
        for spec in TABLE_SPECS:
            connection.execute(delete(spec.target))

    @staticmethod
    def write_batch(
        connection: Connection,
        spec: TableSpec,
        rows: list[dict],
    ) -> int:
        if not rows:
            return 0
        statement = insert(spec.target).values(rows)
        if spec.strategy is LoadStrategy.APPEND_ID:
            statement = statement.on_conflict_do_nothing(index_elements=[spec.target.c.id])
        else:
            updates = {
                column.name: getattr(statement.excluded, column.name)
                for column in spec.target.columns
                if column.name != "id"
            }
            statement = statement.on_conflict_do_update(
                index_elements=[spec.target.c.id],
                set_=updates,
            )
        result = connection.execute(statement)
        return int(result.rowcount or 0)

    @staticmethod
    def record_seen_ids(
        connection: Connection,
        source_table: str,
        source_ids: Iterable[int],
    ) -> None:
        rows = [
            {"source_table": source_table, "source_id": source_id}
            for source_id in source_ids
        ]
        if rows:
            connection.execute(
                text(
                    "INSERT INTO etl_seen_ids (source_table, source_id) "
                    "VALUES (:source_table, :source_id) ON CONFLICT DO NOTHING"
                ),
                rows,
            )

    @staticmethod
    def delete_unseen(connection: Connection, spec: TableSpec) -> int:
        result = connection.execute(
            text(
                f'DELETE FROM raw."{spec.target.name}" AS target '
                "WHERE NOT EXISTS (SELECT 1 FROM etl_seen_ids AS seen "
                "WHERE seen.source_table = :source_table "
                "AND seen.source_id = target.id)"
            ),
            {"source_table": spec.source_name},
        )
        return int(result.rowcount or 0)

    def get_watermark(self, connection: Connection, spec: TableSpec) -> int:
        value = connection.execute(
            select(etl_watermark.c.watermark_value).where(
                etl_watermark.c.source_system == self.source_system,
                etl_watermark.c.source_table == spec.source_name,
            )
        ).scalar_one_or_none()
        return int(value or 0)

    def set_watermark(
        self,
        connection: Connection,
        spec: TableSpec,
        run_id: UUID,
        watermark_value: int | None,
    ) -> None:
        statement = insert(etl_watermark).values(
            source_system=self.source_system,
            source_table=spec.source_name,
            strategy=spec.strategy.value,
            watermark_column="id" if spec.strategy is LoadStrategy.APPEND_ID else None,
            watermark_value=watermark_value,
            updated_at=datetime.now(timezone.utc),
            etl_run_id=run_id,
        )
        connection.execute(
            statement.on_conflict_do_update(
                index_elements=[
                    etl_watermark.c.source_system,
                    etl_watermark.c.source_table,
                ],
                set_={
                    "strategy": statement.excluded.strategy,
                    "watermark_column": statement.excluded.watermark_column,
                    "watermark_value": statement.excluded.watermark_value,
                    "updated_at": statement.excluded.updated_at,
                    "etl_run_id": statement.excluded.etl_run_id,
                },
            )
        )

    @staticmethod
    def count(connection: Connection, spec: TableSpec) -> int:
        return int(connection.execute(select(func.count()).select_from(spec.target)).scalar_one())
