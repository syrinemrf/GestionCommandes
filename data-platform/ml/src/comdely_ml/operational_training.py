from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
from sqlalchemy import create_engine

from .benchmark import run as run_benchmark
from .config import Settings
from .features import build_demand_features
from .loader import load_all_daily_demand
from .modeling import FEATURES, build_pipeline
from .registry import RegisteredModel, register_model


def train(*, seed: int, version: str, artifact_dir: Path, report: Path) -> dict[str, object]:
    settings = Settings.from_env()
    result_path = artifact_dir / f'{version}-metrics.json'
    benchmark = run_benchmark(seed=seed, artifact_dir=artifact_dir, result_path=result_path, report_path=report)
    demand = load_all_daily_demand(settings)
    prepared = build_demand_features(demand)
    mature = prepared.loc[prepared['target_demand_7d'].notna()].copy()
    if mature.empty:
        raise RuntimeError('No mature demand history is available for training')

    parameters = dict(benchmark['validation']['selected_hgb_parameters'])
    q90_pipeline = build_pipeline(seed=seed, loss='quantile', quantile=.9, **parameters)
    q90_pipeline.fit(mature[FEATURES], mature['target_demand_7d'])
    alpha = float(benchmark['validation']['selected_croston_alpha'])
    artifact = artifact_dir / f'{version}.joblib'
    artifact.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump({
        'version': version,
        'algorithm': 'CROSTON_SBA_WITH_HGB_Q90',
        'croston_alpha': alpha,
        'q90_pipeline': q90_pipeline,
        'features': FEATURES,
        'seed': seed,
    }, artifact)

    trained_at = datetime.now(timezone.utc)
    registry_model = RegisteredModel(
        version=version,
        algorithm='CROSTON_SBA_WITH_HGB_Q90',
        trained_at=trained_at,
        training_end_date=mature['demand_date'].max().date(),
        features=FEATURES,
        parameters={'seed': seed, 'croston_alpha': alpha, 'q90': parameters},
        metrics={
            'seasonal_baseline': benchmark['test']['seasonal_baseline']['overall'],
            'croston_sba': benchmark['test']['croston_sba']['overall'],
            'hist_gradient_boosting_q90': benchmark['test']['hist_gradient_boosting_q90']['overall'],
        },
        artifact_path=artifact.resolve(),
    )
    engine = create_engine(settings.database_url, pool_pre_ping=True)
    try:
        with engine.begin() as connection:
            register_model(connection, registry_model)
    finally:
        engine.dispose()
    return {'version': version, 'artifact': str(artifact.resolve()), 'training_rows': len(mature), 'training_end_date': str(registry_model.training_end_date)}


def main() -> int:
    parser = argparse.ArgumentParser(description='Train and register the Comdely operational champion')
    parser.add_argument('--seed', type=int, default=20260804)
    parser.add_argument('--version')
    parser.add_argument('--artifact-dir', type=Path, default=Path('artifacts'))
    parser.add_argument('--report', type=Path, default=Path('reports/model_comparison.md'))
    args = parser.parse_args()
    version = args.version or f"croston-hgb-{datetime.now(timezone.utc):%Y%m%dT%H%M%SZ}"
    try:
        print(json.dumps(train(seed=args.seed, version=version, artifact_dir=args.artifact_dir, report=args.report), indent=2))
    except Exception as error:
        print(json.dumps({'status': 'error', 'message': f'{type(error).__name__}: {error}'}))
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
