import pytest
from sqlalchemy.exc import SQLAlchemyError

from comdely_elt.config import Settings
from comdely_elt.database import check_connection, create_source_engine


def test_source_connection_failure_is_reported() -> None:
    settings = Settings(
        source_database_url=(
            "mysql+pymysql://invalid:invalid@127.0.0.1:1/missing"
            "?connect_timeout=1"
        ),
        target_database_url=(
            "postgresql+psycopg://invalid:invalid@127.0.0.1:1/missing"
        ),
    )
    engine = create_source_engine(settings)

    try:
        with pytest.raises(SQLAlchemyError):
            check_connection(engine)
    finally:
        engine.dispose()
