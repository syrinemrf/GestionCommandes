from airflow.models import DagBag


def test_comdely_dw_daily_imports_without_errors() -> None:
    dag_bag = DagBag(dag_folder="/opt/airflow/dags", include_examples=False)

    assert dag_bag.import_errors == {}
    assert "comdely_dw_daily" in dag_bag.dags

    dag = dag_bag.dags["comdely_dw_daily"]
    assert dag.catchup is False
    assert dag.max_active_runs == 1
    assert {task.task_id for task in dag.tasks} == {
        "check_databases",
        "incremental_raw_load",
        "verify_raw_counts",
        "dbt_build",
        "reconciliation_tests",
        "record_success",
    }
