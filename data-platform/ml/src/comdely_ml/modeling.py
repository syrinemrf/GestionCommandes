from __future__ import annotations

import numpy as np
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.impute import SimpleImputer
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

NUMERIC_FEATURES = [
    'demand_lag_1', 'demand_lag_7', 'demand_lag_14', 'demand_lag_28',
    'rolling_mean_7d', 'rolling_sum_7d', 'rolling_std_7d',
    'rolling_mean_14d', 'rolling_sum_14d', 'rolling_std_14d',
    'rolling_mean_28d', 'rolling_sum_28d', 'rolling_std_28d',
    'recent_trend_7d_vs_28d', 'day_of_week', 'day_of_month',
    'week_of_year', 'month', 'quarter', 'is_weekend', 'day_of_week_sin',
    'day_of_week_cos', 'month_sin', 'month_cos', 'was_zero_previous_day',
    'zero_demand_streak', 'nonzero_days_28d', 'zero_demand_rate_28d',
    'is_intermittent_28d', 'days_since_last_sale',
]
CATEGORICAL_FEATURES = ['source_supplier_id', 'source_product_id', 'source_variation_id']
FEATURES = NUMERIC_FEATURES + CATEGORICAL_FEATURES


def build_pipeline(*, seed: int, loss: str = 'squared_error', quantile: float | None = None, **params: object) -> Pipeline:
    preprocessing = ColumnTransformer([
        ('numeric', SimpleImputer(strategy='median', add_indicator=True), NUMERIC_FEATURES),
        ('categorical', Pipeline([
            ('imputer', SimpleImputer(strategy='most_frequent')),
            ('encoder', OneHotEncoder(handle_unknown='ignore', sparse_output=False, dtype=np.float32)),
        ]), CATEGORICAL_FEATURES),
    ])
    model_params = dict(loss=loss, random_state=seed, max_iter=150, learning_rate=0.08, max_leaf_nodes=31, l2_regularization=1.0)
    model_params.update(params)
    if quantile is not None:
        model_params['quantile'] = quantile
    return Pipeline([('preprocessing', preprocessing), ('model', HistGradientBoostingRegressor(**model_params))])
