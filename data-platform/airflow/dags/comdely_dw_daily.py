from __future__ import annotations

import json
import logging
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any

from airflow.sdk import DAG, get_current_context, task
from sqlalchemy import create_engine, text

from comdely_elt.config import Settings
from comdely_elt.database import create_source_engine, create_target_engine
from comdely_elt.schema import TABLE_SPECS


LOGGER = logging.getLogger(__name__)
DAG_ID = "comdely_dw_daily"
DBT_PROJECT_DIR = Path(os.environ.get("DBT_PROJECT_DIR", "/opt/comdely/dbt"))
DBT_PROFILES_DIR = Path(os.environ.get("DBT_PROFILES_DIR", "/opt/comdely/dbt-profiles"))
RECONCILIATION_TESTS = (
    "assert_mart_daily_kpi_reconciliation",
    "assert_mart_order_status_reconciliation",
    "assert_mart_product_performance_reconciliation",
    "assert_mart_stock_formula",
    "assert_mart_supplier_isolation",
)


def _run_command(command: list[str], *, cwd: Path | None = None) -> str:
    LOGGER.info("Running command: %s", " ".join(command))
    process = subprocess.run(
        command,
        cwd=cwd,
        env=os.environ.copy(),
        capture_output=True,
        text=True,
        check=False,
    )
    if process.stdout:
        for line in process.stdout.splitlines():
            LOGGER.info("%s", line)
    if process.stderr:
        for line in process.stderr.splitlines():
            LOGGER.warning("%s", line)
    if process.returncode != 0:
        raise RuntimeError(
            f"Command failed with exit code {process.returncode}: {' '.join(command)}"
        )
    return process.stdout


def _dw_engine():
    settings = Settings.from_env()
    return create_target_engine(settings)


def _ensure_audit_table(connection) -> None:
    connection.execute(
        text(
            """
            create table if not exists meta.airflow_pipeline_run (
                dag_run_id text primary key,
                dag_id text not null,
                status text not null,
                started_at timestamptz not null,
                finished_at timestamptz,
                logical_date timestamptz,
                etl_run_id uuid,
                failed_task_id text,
                error_message text,
                constraint airflow_pipeline_run_status_check
                    check (status in ('RUNNING', 'SUCCEEDED', 'FAILED'))
            )
            """
        )
    )


def _context_run_id(context: dict[str, Any]) -> str:
    dag_run = context.get("dag_run")
    return dag_run.run_id if dag_run is not None else "unknown"


def _record_running(context: dict[str, Any]) -> None:
    engine = _dw_engine()
    try:
        with engine.begin() as connection:
            _ensure_audit_table(connection)
            connection.execute(
                text(
                    """
                    insert into meta.airflow_pipeline_run (
                        dag_run_id, dag_id, status, started_at, logical_date
                    ) values (
                        :dag_run_id, :dag_id, 'RUNNING', now(), :logical_date
                    )
                    on conflict (dag_run_id) do update set
                        status = 'RUNNING',
                        started_at = excluded.started_at,
                        finished_at = null,
                        etl_run_id = null,
                        failed_task_id = null,
                        error_message = null
                    """
                ),
                {
                    "dag_run_id": _context_run_id(context),
                    "dag_id": DAG_ID,
                    "logical_date": context.get("logical_date"),
                },
            )
    finally:
        engine.dispose()


def _record_failure(context: dict[str, Any]) -> None:
    error = context.get("exception")
    task_instance = context.get("task_instance")
    failed_task_id = task_instance.task_id if task_instance is not None else None
    message = f"{type(error).__name__}: {error}" if error else "DAG run failed"
    LOGGER.error("Pipeline failed at task %s: %s", failed_task_id, message)

    try:
        engine = _dw_engine()
        try:
            with engine.begin() as connection:
                _ensure_audit_table(connection)
                connection.execute(
                    text(
                        """
                        insert into meta.airflow_pipeline_run (
                            dag_run_id, dag_id, status, started_at, finished_at,
                            logical_date, failed_task_id, error_message
                        ) values (
                            :dag_run_id, :dag_id, 'FAILED', now(), now(),
                            :logical_date, :failed_task_id, :error_message
                        )
                        on conflict (dag_run_id) do update set
                            status = 'FAILED',
                            finished_at = now(),
                            failed_task_id = excluded.failed_task_id,
                            error_message = excluded.error_message
                        """
                    ),
                    {
                        "dag_run_id": _context_run_id(context),
                        "dag_id": DAG_ID,
                        "logical_date": context.get("logical_date"),
                        "failed_task_id": failed_task_id,
                        "error_message": message[:4000],
                    },
                )
        finally:
            engine.dispose()
    except Exception:
        LOGGER.exception("Could not write the failure to the DW audit table")


with DAG(
    dag_id=DAG_ID,
    description="Incremental MariaDB to PostgreSQL ELT followed by dbt marts",
    schedule=os.environ.get("COMDELY_DW_SCHEDULE", "0 2 * * *"),
    start_date=datetime(2024, 1, 1, tzinfo=timezone.utc),
    catchup=False,
    max_active_runs=1,
    default_args={
        "owner": "comdely-data",
        "retries": 1,
        "retry_delay": timedelta(minutes=3),
    },
    on_failure_callback=_record_failure,
    tags=["comdely", "data-warehouse", "daily"],
) as dag:

    @task(task_id="check_databases")
    def check_databases() -> None:
        settings = Settings.from_env()
        source_engine = create_source_engine(settings)
        target_engine = create_target_engine(settings)
        try:
            with source_engine.connect() as source_connection:
                source_connection.execute(text("select 1"))
            LOGGER.info("MariaDB source is available in read-only mode")

            with target_engine.connect() as target_connection:
                target_connection.execute(text("select 1"))
                schemas = {
                    row[0]
                    for row in target_connection.execute(
                        text(
                            "select schema_name from information_schema.schemata "
                            "where schema_name in ('raw', 'analytics', 'marts', 'meta')"
                        )
                    )
                }
                missing = {"raw", "analytics", "marts", "meta"} - schemas
                if missing:
                    raise RuntimeError(f"Missing DW schemas: {sorted(missing)}")
            LOGGER.info("PostgreSQL DW is available and required schemas exist")
        finally:
            source_engine.dispose()
            target_engine.dispose()

        _record_running(get_current_context())

    @task(task_id="incremental_raw_load")
    def incremental_raw_load() -> dict[str, Any]:
        output = _run_command(["python", "-m", "comdely_elt", "incremental"])
        try:
            result = json.loads(output)
        except json.JSONDecodeError as error:
            raise RuntimeError("ELT did not return its expected JSON summary") from error
        LOGGER.info("Incremental ELT run id: %s", result.get("run_id"))
        return result

    @task(task_id="verify_raw_counts")
    def verify_raw_counts(elt_result: dict[str, Any]) -> str:
        run_id = str(elt_result["run_id"])
        engine = _dw_engine()
        try:
            with engine.begin() as connection:
                run = connection.execute(
                    text(
                        """
                        select status, source_counts, target_counts
                        from meta.etl_run
                        where id = cast(:run_id as uuid)
                        """
                    ),
                    {"run_id": run_id},
                ).mappings().one_or_none()
                if run is None:
                    raise RuntimeError(f"ELT run {run_id} was not recorded")
                if run["status"] != "SUCCEEDED":
                    raise RuntimeError(f"ELT run {run_id} status is {run['status']}")
                if run["source_counts"] != run["target_counts"]:
                    raise RuntimeError(
                        f"ELT count mismatch: source={run['source_counts']} "
                        f"target={run['target_counts']}"
                    )

                for spec in TABLE_SPECS:
                    actual_count = connection.execute(
                        text(f'SELECT count(*) FROM raw."{spec.target.name}"')
                    ).scalar_one()
                    expected_count = int(run["target_counts"][spec.source_name])
                    if actual_count != expected_count:
                        raise RuntimeError(
                            f"raw.{spec.target.name}: expected={expected_count}, "
                            f"actual={actual_count}"
                        )
                    LOGGER.info(
                        "raw.%s count verified: %s", spec.target.name, actual_count
                    )

                _ensure_audit_table(connection)
                connection.execute(
                    text(
                        """
                        update meta.airflow_pipeline_run
                        set etl_run_id = cast(:etl_run_id as uuid)
                        where dag_run_id = :dag_run_id
                        """
                    ),
                    {
                        "etl_run_id": run_id,
                        "dag_run_id": _context_run_id(get_current_context()),
                    },
                )
        finally:
            engine.dispose()
        return run_id

    @task(task_id="dbt_build")
    def dbt_build() -> None:
        _run_command(
            [
                "dbt",
                "build",
                "--project-dir",
                str(DBT_PROJECT_DIR),
                "--profiles-dir",
                str(DBT_PROFILES_DIR),
                "--no-use-colors",
            ],
            cwd=DBT_PROJECT_DIR,
        )

    @task(task_id="reconciliation_tests")
    def reconciliation_tests() -> None:
        selection = " ".join(RECONCILIATION_TESTS)
        _run_command(
            [
                "dbt",
                "test",
                "--project-dir",
                str(DBT_PROJECT_DIR),
                "--profiles-dir",
                str(DBT_PROFILES_DIR),
                "--select",
                selection,
                "--no-use-colors",
            ],
            cwd=DBT_PROJECT_DIR,
        )

    @task(task_id="record_success")
    def record_success(etl_run_id: str) -> None:
        engine = _dw_engine()
        try:
            with engine.begin() as connection:
                _ensure_audit_table(connection)
                result = connection.execute(
                    text(
                        """
                        update meta.airflow_pipeline_run
                        set status = 'SUCCEEDED',
                            finished_at = now(),
                            etl_run_id = cast(:etl_run_id as uuid),
                            failed_task_id = null,
                            error_message = null
                        where dag_run_id = :dag_run_id
                          and status = 'RUNNING'
                        """
                    ),
                    {
                        "etl_run_id": etl_run_id,
                        "dag_run_id": _context_run_id(get_current_context()),
                    },
                )
                if result.rowcount != 1:
                    raise RuntimeError("Could not mark the Airflow pipeline run as succeeded")
        finally:
            engine.dispose()
        LOGGER.info("Pipeline completed successfully with ELT run %s", etl_run_id)

    databases_ready = check_databases()
    elt_summary = incremental_raw_load()
    counts_verified = verify_raw_counts(elt_summary)
    marts_built = dbt_build()
    reconciled = reconciliation_tests()
    success_recorded = record_success(counts_verified)

    databases_ready >> elt_summary
    counts_verified >> marts_built >> reconciled >> success_recorded
