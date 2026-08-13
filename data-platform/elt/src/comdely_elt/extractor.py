from __future__ import annotations

from collections.abc import Iterator

from sqlalchemy import text
from sqlalchemy.engine import Connection

from comdely_elt.schema import TableSpec


class SourceReader:
    """Read-only, keyset-paginated access to the audited MariaDB tables."""

    def __init__(self, connection: Connection, batch_size: int) -> None:
        self.connection = connection
        self.batch_size = batch_size

    def count(self, spec: TableSpec) -> int:
        statement = text(f"SELECT COUNT(*) FROM `{spec.source_name}`")
        return int(self.connection.execute(statement).scalar_one())

    def iter_batches(
        self,
        spec: TableSpec,
        after_id: int = 0,
    ) -> Iterator[list[dict]]:
        columns = ", ".join(f"`{name}`" for name in spec.source_columns)
        statement = text(
            f"SELECT {columns} FROM `{spec.source_name}` "
            "WHERE `id` > :last_id ORDER BY `id` ASC LIMIT :batch_size"
        )
        last_id = after_id

        while True:
            rows = self.connection.execute(
                statement,
                {"last_id": last_id, "batch_size": self.batch_size},
            ).mappings().all()
            if not rows:
                return
            batch = [dict(row) for row in rows]
            yield batch
            last_id = int(batch[-1]["id"])
