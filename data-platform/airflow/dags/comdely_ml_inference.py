from __future__ import annotations

import json
import logging
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path

from airflow.sdk import DAG, task
from sqlalchemy import create_engine, text

from comdely_ml.config import Settings


LOGGER = logging.getLogger(__name__)
DBT_PROJECT_DIR = Path(os.environ.get('DBT_PROJECT_DIR', '/opt/comdely/dbt'))
DBT_PROFILES_DIR = Path(os.environ.get('DBT_PROFILES_DIR', '/opt/comdely/dbt-profiles'))


def _run(command: list[str], cwd: Path | None = None) -> str:
    process = subprocess.run(command, cwd=cwd, env=os.environ.copy(), capture_output=True, text=True, check=False)
    if process.stdout:
        LOGGER.info('%s', process.stdout)
    if process.stderr:
        LOGGER.warning('%s', process.stderr)
    if process.returncode:
        raise RuntimeError(f"Command failed ({process.returncode}): {' '.join(command)}")
    return process.stdout


with DAG(
    dag_id='comdely_ml_inference',
    description='Idempotent stock risk inference after the DW refresh',
    schedule=None,
    start_date=datetime(2024, 1, 1, tzinfo=timezone.utc),
    catchup=False,
    max_active_runs=1,
    default_args={'owner': 'comdely-data', 'retries': 1, 'retry_delay': timedelta(minutes=3)},
    tags=['comdely', 'ml', 'inference'],
) as dag:

    @task(task_id='apply_ml_migrations')
    def migrate() -> None:
        _run(['python', '-m', 'comdely_ml.migrations', '--directory', os.environ.get('ML_MIGRATIONS_DIR', '/opt/comdely/postgres-migrations')])

    @task(task_id='generate_predictions')
    def predict() -> dict[str, object]:
        output = _run(['python', '-m', 'comdely_ml.inference'])
        result = json.loads(output)
        if result.get('stock_writes') != 0:
            raise RuntimeError('Inference reported an unexpected stock write')
        return result

    @task(task_id='build_stock_risk_mart')
    def build_mart() -> None:
        _run(['dbt', 'build', '--project-dir', str(DBT_PROJECT_DIR), '--profiles-dir', str(DBT_PROFILES_DIR), '--select', 'mart_supplier_stock_risk', '--no-use-colors'], DBT_PROJECT_DIR)

    @task(task_id='verify_predictions')
    def verify(result: dict[str, object]) -> None:
        engine = create_engine(Settings.from_env().database_url, pool_pre_ping=True)
        try:
            with engine.connect() as connection:
                count = connection.execute(text('select count(*) from analytics.mart_supplier_stock_risk where model_version = :version'), {'version': result['model_version']}).scalar_one()
            if int(count) != int(result['variations']):
                raise RuntimeError(f"Prediction/mart count mismatch: {result['variations']} != {count}")
        finally:
            engine.dispose()

    migrated = migrate()
    predictions = predict()
    mart = build_mart()
    checked = verify(predictions)
    migrated >> predictions >> mart >> checked
