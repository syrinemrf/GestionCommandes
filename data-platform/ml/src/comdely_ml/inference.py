from __future__ import annotations

import argparse
import json
import math
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sqlalchemy import create_engine, text

from .config import Settings
from .croston import croston_sba_next
from .features import build_demand_features
from .explanations import business_explanation, tree_pipeline_explanations
from .loader import load_all_daily_demand
from .registry import champion


MIN_HISTORY_DAYS = 28
MIN_NONZERO_DAYS = 2


def stock_decision(central: float, q90: float, stock_available: int, *, sufficient_data: bool) -> tuple[str, int, float, float, int]:
    stock = max(0, int(stock_available))
    point = max(0.0, float(central))
    upper = max(point, float(q90), 0.0)
    if not sufficient_data:
        return 'INSUFFICIENT_DATA', 0, 0.0, 0.0, stock
    if stock < point:
        risk = 'HIGH'
    elif stock < upper:
        risk = 'MEDIUM'
    else:
        risk = 'LOW'
    return risk, max(0, math.ceil(upper - stock)), point, upper, stock


def _stock(connection) -> pd.DataFrame:
    return pd.read_sql_query(
        text(
            '''
            select source_system, source_supplier_id, source_product_id,
                   source_variation_id, stock_registered, stock_used, stock_reserved
            from analytics.mart_supplier_stock_overview
            where not product_is_deleted and not variation_is_deleted
            '''
        ),
        connection,
    )


def infer() -> dict[str, object]:
    settings = Settings.from_env()
    engine = create_engine(settings.database_url, pool_pre_ping=True)
    try:
        with engine.connect() as connection:
            registered = champion(connection)
            stocks = _stock(connection)
        artifact_path = Path(str(registered['artifact_path']))
        if not artifact_path.is_file():
            raise RuntimeError(f'Champion artifact does not exist: {artifact_path}')
        bundle = joblib.load(artifact_path)
        if bundle.get('version') != registered['version']:
            raise RuntimeError('Artifact version does not match the model registry')

        demand = load_all_daily_demand(settings)
        if demand.empty:
            raise RuntimeError('The demand mart is empty')
        prepared = build_demand_features(demand)
        latest = prepared.sort_values('demand_date').groupby('variation_key', as_index=False).tail(1).copy()
        history = {key: group.sort_values('demand_date') for key, group in demand.groupby('variation_key', sort=False)}
        latest['history_days'] = latest['variation_key'].map(lambda key: len(history[key]))
        latest['nonzero_days'] = latest['variation_key'].map(lambda key: int(history[key]['demand_quantity'].gt(0).sum()))
        latest['sufficient_data'] = latest['history_days'].ge(MIN_HISTORY_DAYS) & latest['nonzero_days'].ge(MIN_NONZERO_DAYS)
        latest['forecast_central_7d'] = latest['variation_key'].map(
            lambda key: croston_sba_next(history[key]['demand_quantity'], alpha=float(bundle['croston_alpha']))
        )
        latest['forecast_q90_7d'] = 0.0
        sufficient = latest['sufficient_data']
        if sufficient.any():
            latest.loc[sufficient, 'forecast_q90_7d'] = np.clip(
                bundle['q90_pipeline'].predict(latest.loc[sufficient, bundle['features']]),
                0,
                None,
            )
        latest['shap_factors'] = None
        if sufficient.any():
            for row_index, factors in zip(
                latest.index[sufficient],
                tree_pipeline_explanations(
                    bundle['q90_pipeline'],
                    latest.loc[sufficient, bundle['features']],
                    bundle['features'],
                ),
                strict=True,
            ):
                latest.at[row_index, 'shap_factors'] = factors

        rows = latest.merge(stocks, on=['source_system', 'source_supplier_id', 'source_product_id', 'source_variation_id'], how='inner')
        if rows.empty:
            raise RuntimeError('No active variation has both demand and stock data')
        prediction_date = rows['demand_date'].max().date()
        predicted_at = datetime.now(timezone.utc)
        values = []
        risk_counts: dict[str, int] = {}
        for row_index, row in rows.iterrows():
            available = max(0, int(row.stock_registered) - int(row.stock_used) - int(row.stock_reserved))
            risk, recommendation, central, q90, available = stock_decision(
                row['forecast_central_7d'],
                row['forecast_q90_7d'],
                available,
                sufficient_data=bool(row['sufficient_data']),
            )
            risk_counts[risk] = risk_counts.get(risk, 0) + 1
            values.append({
                'prediction_date': prediction_date,
                'predicted_at': predicted_at,
                'source_system': row['source_system'],
                'source_supplier_id': int(row['source_supplier_id']),
                'source_product_id': int(row['source_product_id']),
                'source_variation_id': int(row['source_variation_id']),
                'forecast_central_7d': round(central, 4),
                'forecast_q90_7d': round(q90, 4),
                'stock_available': available,
                'risk': risk,
                'recommended_quantity': recommendation,
                'model_version': registered['version'],
                'business_explanation': json.dumps(business_explanation(
                    stock_available=available,
                    forecast_central_7d=central,
                    forecast_q90_7d=q90,
                    risk=risk,
                    recommended_quantity=recommendation,
                )),
                'shap_factors': json.dumps(row['shap_factors']) if row['shap_factors'] is not None else None,
            })
        with engine.begin() as connection:
            connection.execute(
                text(
                    '''
                    insert into ml.stock_prediction (
                        prediction_date, predicted_at, source_system,
                        source_supplier_id, source_product_id, source_variation_id,
                        forecast_central_7d, forecast_q90_7d, stock_available,
                        risk, recommended_quantity, model_version,
                        business_explanation, shap_factors
                    ) values (
                        :prediction_date, :predicted_at, :source_system,
                        :source_supplier_id, :source_product_id, :source_variation_id,
                        :forecast_central_7d, :forecast_q90_7d, :stock_available,
                        :risk, :recommended_quantity, :model_version,
                        cast(:business_explanation as jsonb), cast(:shap_factors as jsonb)
                    )
                    on conflict (prediction_date, source_system, source_variation_id)
                    do update set
                        predicted_at = excluded.predicted_at,
                        source_supplier_id = excluded.source_supplier_id,
                        source_product_id = excluded.source_product_id,
                        forecast_central_7d = excluded.forecast_central_7d,
                        forecast_q90_7d = excluded.forecast_q90_7d,
                        stock_available = excluded.stock_available,
                        risk = excluded.risk,
                        recommended_quantity = excluded.recommended_quantity,
                        model_version = excluded.model_version,
                        business_explanation = excluded.business_explanation,
                        shap_factors = excluded.shap_factors
                    '''
                ),
                values,
            )
        return {'model_version': registered['version'], 'prediction_date': str(prediction_date), 'variations': len(values), 'risks': risk_counts, 'stock_writes': 0}
    finally:
        engine.dispose()


def main() -> int:
    argparse.ArgumentParser(description='Generate idempotent stock risk predictions without modifying stock').parse_args()
    try:
        print(json.dumps(infer(), indent=2))
    except Exception as error:
        print(json.dumps({'status': 'error', 'message': f'{type(error).__name__}: {error}'}))
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
