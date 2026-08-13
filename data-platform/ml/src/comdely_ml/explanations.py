from __future__ import annotations

from collections.abc import Sequence

import numpy as np
import pandas as pd
import shap
from sklearn.pipeline import Pipeline


DIRECTION_INCREASES = 'INCREASES'
DIRECTION_DECREASES = 'DECREASES'
DIRECTION_NEUTRAL = 'NEUTRAL'


def business_explanation(
    *,
    stock_available: int,
    forecast_central_7d: float,
    forecast_q90_7d: float,
    risk: str,
    recommended_quantity: int,
) -> dict[str, int | float | str]:
    """Return the deterministic inputs and result of the stock decision."""
    return {
        'stock_available': max(0, int(stock_available)),
        'forecast_central_7d': round(max(0.0, float(forecast_central_7d)), 4),
        'forecast_q90_7d': round(max(0.0, float(forecast_q90_7d)), 4),
        'deficit': max(0, int(np.ceil(float(forecast_central_7d) - int(stock_available)))),
        'risk': risk,
        'recommended_quantity': max(0, int(recommended_quantity)),
    }


def tree_pipeline_explanations(
    pipeline: Pipeline,
    rows: pd.DataFrame,
    original_features: Sequence[str],
    *,
    top_n: int = 3,
) -> list[list[dict[str, object]]]:
    """Compute and aggregate SHAP contributions outside the web application."""
    if rows.empty:
        return []
    preprocessing = pipeline.named_steps['preprocessing']
    model = pipeline.named_steps['model']
    transformed = preprocessing.transform(rows[list(original_features)])
    transformed_names = list(preprocessing.get_feature_names_out())
    if transformed.shape[1] != len(transformed_names):
        raise ValueError('Transformed feature count does not match feature names')

    shap_values = np.asarray(shap.TreeExplainer(model).shap_values(transformed))
    if shap_values.ndim == 1:
        shap_values = shap_values.reshape(1, -1)
    if shap_values.shape != transformed.shape:
        raise ValueError('SHAP contribution count does not match transformed features')

    logical_names = [_logical_feature_name(name, original_features) for name in transformed_names]
    if not set(logical_names).issubset(set(original_features)):
        raise ValueError('SHAP contains an unknown logical feature')

    explanations: list[list[dict[str, object]]] = []
    for row_number, contributions in enumerate(shap_values):
        grouped = {name: 0.0 for name in original_features}
        for name, contribution in zip(logical_names, contributions, strict=True):
            grouped[name] += float(contribution)
        ranked = sorted(grouped.items(), key=lambda item: abs(item[1]), reverse=True)[:top_n]
        explanations.append([
            {
                'name': name,
                'value': _json_value(rows.iloc[row_number][name]),
                'contribution': round(contribution, 6),
                'direction': _direction(contribution),
            }
            for name, contribution in ranked
        ])
    return explanations


def _logical_feature_name(transformed_name: str, original_features: Sequence[str]) -> str:
    name = transformed_name.split('__', 1)[-1]
    if name.startswith('missingindicator_'):
        name = name.removeprefix('missingindicator_')
    if name in original_features:
        return name
    candidates = [feature for feature in original_features if name.startswith(f'{feature}_')]
    if candidates:
        return max(candidates, key=len)
    raise ValueError(f'Cannot map transformed feature {transformed_name!r}')


def _json_value(value: object) -> int | float | str | None:
    if pd.isna(value):
        return None
    if isinstance(value, (np.integer, int)):
        return int(value)
    if isinstance(value, (np.floating, float)):
        return round(float(value), 6)
    return str(value)


def _direction(value: float) -> str:
    if value > 0:
        return DIRECTION_INCREASES
    if value < 0:
        return DIRECTION_DECREASES
    return DIRECTION_NEUTRAL
