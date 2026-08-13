from __future__ import annotations

import os
from datetime import datetime, timezone
from decimal import Decimal
from uuid import uuid4

import pytest
from sqlalchemy import delete, func, select

from comdely_elt.config import Settings
from comdely_elt.database import create_target_engine
from comdely_elt.loader import TargetStore
from comdely_elt.schema import (
    TABLE_SPEC_BY_NAME,
    etl_run,
    raw_historique_statut,
    raw_parametre,
)


@pytest.fixture()
def target_connection():
    target_url = os.getenv("TEST_DW_DATABASE_URL") or os.getenv("DW_DATABASE_URL")
    if not target_url:
        pytest.skip("TEST_DW_DATABASE_URL or DW_DATABASE_URL is required")

    settings = Settings(
        source_database_url="mysql+pymysql://unused:unused@127.0.0.1/unused",
        target_database_url=target_url,
        source_system="pytest",
    )
    engine = create_target_engine(settings)
    store = TargetStore(engine, settings.source_system)
    store.bootstrap()
    connection = engine.connect()
    transaction = connection.begin()
    run_id = uuid4()
    connection.execute(
        etl_run.insert().values(
            id=run_id,
            mode="test",
            status="RUNNING",
            source_system="pytest",
            started_at=datetime.now(timezone.utc),
        )
    )
    try:
        yield connection, store, run_id
    finally:
        transaction.rollback()
        connection.close()
        engine.dispose()


def _audit(run_id):
    return {
        "extracted_at": datetime.now(timezone.utc),
        "etl_run_id": run_id,
    }


def test_upsert_updates_without_creating_duplicates(target_connection) -> None:
    connection, store, run_id = target_connection
    spec = TABLE_SPEC_BY_NAME["parametre"]
    connection.execute(delete(raw_parametre).where(raw_parametre.c.id == -1))

    first = {
        "id": -1,
        "numero_commande": 10,
        "tva": Decimal("19.000"),
        **_audit(run_id),
    }
    second = {**first, "numero_commande": 11}
    store.write_batch(connection, spec, [first])
    store.write_batch(connection, spec, [second])

    row = connection.execute(
        select(raw_parametre).where(raw_parametre.c.id == -1)
    ).mappings().one()
    count = connection.execute(
        select(func.count()).select_from(raw_parametre).where(raw_parametre.c.id == -1)
    ).scalar_one()
    assert row["numero_commande"] == 11
    assert count == 1


def test_append_is_idempotent(target_connection) -> None:
    connection, store, run_id = target_connection
    spec = TABLE_SPEC_BY_NAME["historique_statut_commande"]
    connection.execute(
        delete(raw_historique_statut).where(raw_historique_statut.c.id == -1)
    )
    row = {
        "id": -1,
        "commande_id": 1,
        "ancien_statut": None,
        "nouveau_statut": "EN_ATTENTE_CONFIRMATION",
        "changed_at": datetime.now(timezone.utc),
        "changed_by_id": 1,
        **_audit(run_id),
    }

    store.write_batch(connection, spec, [row])
    store.write_batch(connection, spec, [row])

    count = connection.execute(
        select(func.count())
        .select_from(raw_historique_statut)
        .where(raw_historique_statut.c.id == -1)
    ).scalar_one()
    assert count == 1


def test_watermark_can_be_resumed_and_advanced(target_connection) -> None:
    connection, store, run_id = target_connection
    spec = TABLE_SPEC_BY_NAME["historique_statut_commande"]

    store.set_watermark(connection, spec, run_id, 42)
    assert store.get_watermark(connection, spec) == 42
    store.set_watermark(connection, spec, run_id, 84)
    assert store.get_watermark(connection, spec) == 84
