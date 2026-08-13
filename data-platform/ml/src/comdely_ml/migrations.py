from __future__ import annotations

import argparse
from pathlib import Path

from sqlalchemy import create_engine

from .config import Settings


def apply_migrations(directory: Path, settings: Settings | None = None) -> list[str]:
    configuration = settings or Settings.from_env()
    files = sorted(directory.glob('*.sql'))
    if not files:
        raise ValueError(f'No SQL migration found in {directory}')
    engine = create_engine(configuration.database_url, pool_pre_ping=True)
    applied: list[str] = []
    try:
        with engine.connect() as connection:
            for migration in files:
                connection.exec_driver_sql(migration.read_text(encoding='utf-8'))
                connection.commit()
                applied.append(migration.name)
    finally:
        engine.dispose()
    return applied


def main() -> int:
    parser = argparse.ArgumentParser(description='Apply idempotent Comdely ML DW migrations')
    parser.add_argument('--directory', type=Path, default=Path('data-platform/postgres/migrations'))
    args = parser.parse_args()
    try:
        applied = apply_migrations(args.directory)
    except Exception as error:
        print(f'ML migration failed: {type(error).__name__}: {error}')
        return 1
    print(f"Applied {len(applied)} migration(s): {', '.join(applied)}")
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
