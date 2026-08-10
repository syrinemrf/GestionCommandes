from __future__ import annotations

import json
from dataclasses import dataclass
from datetime import date, datetime
from pathlib import Path

from sqlalchemy import Connection, text


@dataclass(frozen=True, slots=True)
class RegisteredModel:
    version: str
    algorithm: str
    trained_at: datetime
    training_end_date: date
    features: list[str]
    parameters: dict[str, object]
    metrics: dict[str, object]
    artifact_path: Path
    status: str = 'CHAMPION'


def register_model(connection: Connection, model: RegisteredModel) -> None:
    if model.status not in {'CANDIDATE', 'CHAMPION', 'REJECTED'}:
        raise ValueError(f'Unsupported model status: {model.status}')
    if model.status == 'CHAMPION':
        connection.execute(text("update ml.model_registry set status = 'REJECTED' where status = 'CHAMPION' and version <> :version"), {'version': model.version})
    connection.execute(
        text(
            '''
            insert into ml.model_registry (
                version, algorithm, trained_at, training_end_date, features,
                parameters, metrics, artifact_path, status
            ) values (
                :version, :algorithm, :trained_at, :training_end_date,
                cast(:features as jsonb), cast(:parameters as jsonb),
                cast(:metrics as jsonb), :artifact_path, :status
            )
            on conflict (version) do update set
                algorithm = excluded.algorithm,
                trained_at = excluded.trained_at,
                training_end_date = excluded.training_end_date,
                features = excluded.features,
                parameters = excluded.parameters,
                metrics = excluded.metrics,
                artifact_path = excluded.artifact_path,
                status = excluded.status
            '''
        ),
        {
            'version': model.version,
            'algorithm': model.algorithm,
            'trained_at': model.trained_at,
            'training_end_date': model.training_end_date,
            'features': json.dumps(model.features),
            'parameters': json.dumps(model.parameters),
            'metrics': json.dumps(model.metrics),
            'artifact_path': str(model.artifact_path),
            'status': model.status,
        },
    )


def champion(connection: Connection) -> dict[str, object]:
    row = connection.execute(text("select * from ml.model_registry where status = 'CHAMPION' order by trained_at desc limit 1")).mappings().one_or_none()
    if row is None:
        raise RuntimeError('No CHAMPION model is registered')
    return dict(row)
