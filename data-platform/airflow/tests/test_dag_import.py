from airflow.models import DagBag


def test_comdely_dags_import_without_errors() -> None:
    dag_bag = DagBag(dag_folder="/opt/airflow/dags", include_examples=False)

    assert dag_bag.import_errors == {}
    assert "comdely_dw_daily" in dag_bag.dags
    assert "comdely_ml_inference" in dag_bag.dags
    assert "comdely_ml_training" in dag_bag.dags

    dag = dag_bag.dags["comdely_dw_daily"]
    assert dag.catchup is False
    assert dag.max_active_runs == 1
    assert {task.task_id for task in dag.tasks} == {
        "check_databases",
        "incremental_raw_load",
        "verify_raw_counts",
        "apply_ml_migrations",
        "dbt_build",
        "reconciliation_tests",
        "record_success",
        "trigger_ml_inference",
    }

    inference = dag_bag.dags['comdely_ml_inference']
    assert inference.catchup is False
    assert inference.max_active_runs == 1
    assert inference.schedule is None
    assert {task.task_id for task in inference.tasks} == {
        'apply_ml_migrations', 'generate_predictions',
        'build_stock_risk_mart', 'verify_predictions',
    }

    training = dag_bag.dags['comdely_ml_training']
    assert training.catchup is False
    assert training.max_active_runs == 1
    assert {task.task_id for task in training.tasks} == {
        'apply_ml_migrations', 'train_and_register_champion',
    }
