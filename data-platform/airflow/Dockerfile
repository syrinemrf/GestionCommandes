ARG AIRFLOW_VERSION=3.1.8
FROM apache/airflow:${AIRFLOW_VERSION}-python3.11

ARG AIRFLOW_VERSION

COPY --chown=airflow:0 data-platform/elt/pyproject.toml /tmp/comdely-elt/pyproject.toml
COPY --chown=airflow:0 data-platform/elt/src /tmp/comdely-elt/src
COPY --chown=airflow:0 data-platform/ml/pyproject.toml /tmp/comdely-ml/pyproject.toml
COPY --chown=airflow:0 data-platform/ml/src /tmp/comdely-ml/src
COPY --chown=airflow:0 data-platform/airflow/requirements.txt /tmp/airflow-requirements.txt

RUN pip install --no-cache-dir \
    "apache-airflow==${AIRFLOW_VERSION}" \
    /tmp/comdely-elt \
    /tmp/comdely-ml \
    -r /tmp/airflow-requirements.txt

COPY --chown=airflow:0 data-platform/dbt/comdely_warehouse /opt/comdely/dbt
COPY --chown=airflow:0 data-platform/airflow/dbt-profiles /opt/comdely/dbt-profiles
COPY --chown=airflow:0 data-platform/postgres/migrations /opt/comdely/postgres-migrations

ENV DBT_PROJECT_DIR=/opt/comdely/dbt \
    DBT_PROFILES_DIR=/opt/comdely/dbt-profiles \
    ML_MIGRATIONS_DIR=/opt/comdely/postgres-migrations \
    ML_ARTIFACT_DIR=/opt/comdely/ml-artifacts
