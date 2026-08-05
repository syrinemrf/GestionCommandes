from __future__ import annotations

from sqlalchemy import create_engine, event, text
from sqlalchemy.engine import Engine

from comdely_elt.config import Settings


def create_source_engine(settings: Settings) -> Engine:
    engine = create_engine(
        settings.source_database_url,
        pool_pre_ping=True,
        connect_args={"connect_timeout": 10},
    )

    @event.listens_for(engine, "connect")
    def make_session_read_only(dbapi_connection, _connection_record) -> None:
        cursor = dbapi_connection.cursor()
        try:
            cursor.execute("SET SESSION TRANSACTION READ ONLY")
        finally:
            cursor.close()

    return engine


def create_target_engine(settings: Settings) -> Engine:
    return create_engine(
        settings.target_database_url,
        pool_pre_ping=True,
        connect_args={"connect_timeout": 10},
    )


def check_connection(engine: Engine) -> None:
    with engine.connect() as connection:
        connection.execute(text("SELECT 1"))
