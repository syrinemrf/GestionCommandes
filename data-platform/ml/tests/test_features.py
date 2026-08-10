import pandas as pd
import pytest
from pandas.testing import assert_series_equal

from comdely_ml.features import build_demand_features


def demand_frame(periods: int = 50) -> pd.DataFrame:
    dates = pd.date_range('2024-08-04', periods=periods, freq='D')
    return pd.DataFrame(
        {
            'demand_date': dates,
            'supplier_key': 'supplier-1',
            'product_key': 'product-1',
            'variation_key': 'variation-1',
            'source_supplier_id': 1,
            'source_product_id': 10,
            'source_variation_id': 100,
            'product_created_at': pd.Timestamp('2024-08-04'),
            'demand_quantity': range(1, periods + 1),
        }
    )


def test_lags_rolling_windows_and_target_have_expected_alignment() -> None:
    features = build_demand_features(
        demand_frame(),
        today=pd.Timestamp('2026-08-10'),
    )
    row = features.iloc[30]

    assert row['demand_lag_1'] == 30
    assert row['demand_lag_7'] == 24
    assert row['rolling_sum_7d'] == sum(range(24, 31))
    assert row['rolling_mean_7d'] == sum(range(24, 31)) / 7
    assert row['target_demand_7d'] == sum(range(32, 39))


def test_future_changes_cannot_change_features_at_forecast_date() -> None:
    original = demand_frame()
    cutoff = original.loc[30, 'demand_date']
    changed = original.copy()
    changed.loc[changed['demand_date'] > cutoff, 'demand_quantity'] += 1000

    original_features = build_demand_features(
        original,
        today=pd.Timestamp('2026-08-10'),
    )
    changed_features = build_demand_features(
        changed,
        today=pd.Timestamp('2026-08-10'),
    )
    feature_columns = [
        column
        for column in original_features.columns
        if column not in {'demand_quantity', 'target_demand_7d'}
    ]
    original_row = original_features.loc[
        original_features['demand_date'].eq(cutoff),
        feature_columns,
    ].reset_index(drop=True)
    changed_row = changed_features.loc[
        changed_features['demand_date'].eq(cutoff),
        feature_columns,
    ].reset_index(drop=True)

    assert_series_equal(original_row.iloc[0], changed_row.iloc[0])
    assert (
        original_features.loc[30, 'target_demand_7d']
        != changed_features.loc[30, 'target_demand_7d']
    )


def test_stock_is_not_part_of_the_modeling_features() -> None:
    features = build_demand_features(
        demand_frame(),
        today=pd.Timestamp('2026-08-10'),
    )

    assert not any('stock' in column.lower() for column in features.columns)
    assert {
        'zero_demand_streak',
        'zero_demand_rate_28d',
        'days_since_last_sale',
        'is_intermittent_28d',
    }.issubset(features.columns)


def test_future_dates_are_rejected() -> None:
    with pytest.raises(ValueError, match='future'):
        build_demand_features(
            demand_frame(),
            today=pd.Timestamp('2024-08-10'),
        )
