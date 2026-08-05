from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from uuid import UUID
from zoneinfo import ZoneInfo

from comdely_elt.config import Settings
from comdely_elt.conversion import normalize_row
from comdely_elt.database import create_source_engine, create_target_engine
from comdely_elt.extractor import SourceReader
from comdely_elt.loader import TargetStore
from comdely_elt.schema import LoadStrategy, TABLE_SPECS, TableSpec


@dataclass(frozen=True, slots=True)
class RunResult:
    run_id: UUID
    mode: str
    source_counts: dict[str, int]
    target_counts: dict[str, int]
    loaded_counts: dict[str, int]


class CountMismatchError(RuntimeError):
    pass


class ELTPipeline:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    def run(self, mode: str) -> RunResult:
        if mode not in {"full", "incremental"}:
            raise ValueError("mode must be 'full' or 'incremental'")

        source_engine = create_source_engine(self.settings)
        target_engine = create_target_engine(self.settings)
        store = TargetStore(target_engine, self.settings.source_system)
        store.bootstrap()
        run_id = store.start_run(mode)

        source_counts: dict[str, int] = {}
        target_counts: dict[str, int] = {}
        loaded_counts: dict[str, int] = {}

        try:
            with source_engine.connect() as source_connection:
                reader = SourceReader(source_connection, self.settings.batch_size)
                with target_engine.begin() as target_connection:
                    store.prepare_seen_ids(target_connection)
                    if mode == "full":
                        store.clear_raw_tables(target_connection)

                    for spec in TABLE_SPECS:
                        source_count, loaded_count = self._load_table(
                            reader,
                            target_connection,
                            store,
                            spec,
                            run_id,
                            mode,
                        )
                        target_count = store.count(target_connection, spec)
                        if target_count != source_count:
                            raise CountMismatchError(
                                f"{spec.source_name}: source={source_count}, "
                                f"target={target_count}"
                            )
                        source_counts[spec.source_name] = source_count
                        target_counts[spec.source_name] = target_count
                        loaded_counts[spec.source_name] = loaded_count

                    store.mark_succeeded(
                        target_connection,
                        run_id,
                        source_counts,
                        target_counts,
                        loaded_counts,
                    )
        except Exception as error:
            store.mark_failed(run_id, error)
            raise
        finally:
            source_engine.dispose()
            target_engine.dispose()

        return RunResult(
            run_id=run_id,
            mode=mode,
            source_counts=source_counts,
            target_counts=target_counts,
            loaded_counts=loaded_counts,
        )

    def _load_table(
        self,
        reader: SourceReader,
        target_connection,
        store: TargetStore,
        spec: TableSpec,
        run_id: UUID,
        mode: str,
    ) -> tuple[int, int]:
        source_count = reader.count(spec)
        incremental_append = (
            mode == "incremental" and spec.strategy is LoadStrategy.APPEND_ID
        )
        after_id = store.get_watermark(target_connection, spec) if incremental_append else 0
        max_seen_id = after_id
        loaded_count = 0
        extracted_at = datetime.now(timezone.utc)
        source_timezone = ZoneInfo(self.settings.source_timezone)

        for source_batch in reader.iter_batches(spec, after_id=after_id):
            rows = []
            source_ids = []
            for source_row in source_batch:
                row = normalize_row(spec.target, source_row, source_timezone)
                row["extracted_at"] = extracted_at
                row["etl_run_id"] = run_id
                rows.append(row)
                source_id = int(row["id"])
                source_ids.append(source_id)
                max_seen_id = max(max_seen_id, source_id)

            store.write_batch(target_connection, spec, rows)
            loaded_count += len(rows)
            if spec.strategy is LoadStrategy.UPSERT_SNAPSHOT:
                store.record_seen_ids(
                    target_connection,
                    spec.source_name,
                    source_ids,
                )

        if spec.strategy is LoadStrategy.UPSERT_SNAPSHOT:
            store.delete_unseen(target_connection, spec)
            watermark_value = None
        else:
            watermark_value = max_seen_id

        store.set_watermark(
            target_connection,
            spec,
            run_id,
            watermark_value,
        )
        return source_count, loaded_count
