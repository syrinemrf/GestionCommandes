from __future__ import annotations

import os
from uuid import uuid4

import pytest
from sqlalchemy import delete, select
from sqlalchemy.exc import SQLAlchemyError

from comdely_elt.config import Settings
from comdely_elt.database import create_target_engine
from comdely_elt.pipeline import ELTPipeline
from comdely_elt.schema import etl_run


def test_source_failure_is_recorded_in_etl_run() -> None:
    target_url = os.getenv("TEST_DW_DATABASE_URL") or os.getenv("DW_DATABASE_URL")
    if not target_url:
        pytest.skip("TEST_DW_DATABASE_URL or DW_DATABASE_URL is required")

    source_system = f"pytest_failure_{uuid4().hex}"
    settings = Settings(
        source_database_url="mysql+pymysql://invalid:invalid@127.0.0.1:1/missing",
        target_database_url=target_url,
        source_system=source_system,
    )

    with pytest.raises(SQLAlchemyError):
        ELTPipeline(settings).run("incremental")

    engine = create_target_engine(settings)
    try:
        with engine.begin() as connection:
            run = connection.execute(
                select(etl_run).where(etl_run.c.source_system == source_system)
            ).mappings().one()
            assert run["status"] == "FAILED"
            assert "OperationalError" in run["error_message"]
            connection.execute(
                delete(etl_run).where(etl_run.c.source_system == source_system)
            )
    finally:
        engine.dispose()
