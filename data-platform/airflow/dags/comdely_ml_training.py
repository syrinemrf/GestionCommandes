from __future__ import annotations

import json
import logging
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path

from airflow.sdk import DAG, get_current_context, task


LOGGER = logging.getLogger(__name__)


def _run(command: list[str]) -> str:
    process = subprocess.run(command, env=os.environ.copy(), capture_output=True, text=True, check=False)
    if process.stdout:
        LOGGER.info('%s', process.stdout)
    if process.stderr:
        LOGGER.warning('%s', process.stderr)
    if process.returncode:
        raise RuntimeError(f"Command failed ({process.returncode}): {' '.join(command)}")
    return process.stdout


with DAG(
    dag_id='comdely_ml_training',
    description='Weekly or manual training and champion registration',
    schedule=os.environ.get('COMDELY_ML_TRAINING_SCHEDULE', '0 3 * * 0'),
    start_date=datetime(2024, 1, 1, tzinfo=timezone.utc),
    catchup=False,
    max_active_runs=1,
    default_args={'owner': 'comdely-data', 'retries': 0, 'retry_delay': timedelta(minutes=5)},
    tags=['comdely', 'ml', 'training'],
) as dag:

    @task(task_id='apply_ml_migrations')
    def migrate() -> None:
        _run(['python', '-m', 'comdely_ml.migrations', '--directory', os.environ.get('ML_MIGRATIONS_DIR', '/opt/comdely/postgres-migrations')])

    @task(task_id='train_and_register_champion')
    def train() -> dict[str, object]:
        context = get_current_context()
        logical_date = context['logical_date'].astimezone(timezone.utc)
        version = f"croston-hgb-{logical_date:%Y%m%dT%H%M%SZ}"
        directory = Path(os.environ.get('ML_ARTIFACT_DIR', '/opt/comdely/ml-artifacts'))
        output = _run([
            'python', '-m', 'comdely_ml.operational_training',
            '--seed', '20260804', '--version', version,
            '--artifact-dir', str(directory), '--report', str(directory / 'model_comparison.md'),
        ])
        return json.loads(output)

    migrate() >> train()
