import pandas as pd

from comdely_ml.baseline import evaluate_baseline
from comdely_ml.splits import chronological_split


def test_chronological_boundaries_are_inclusive_and_disjoint() -> None:
    frame = pd.DataFrame(
        {
            'demand_date': pd.to_datetime(
                [
                    '2024-08-04',
                    '2025-12-31',
                    '2026-01-01',
                    '2026-05-31',
                    '2026-06-01',
                    '2026-08-04',
                ]
            )
        }
    )
    splits = chronological_split(frame)

    assert len(splits.train) == 2
    assert len(splits.validation) == 2
    assert len(splits.test) == 2
    assert set(splits.train.index).isdisjoint(splits.validation.index)
    assert set(splits.validation.index).isdisjoint(splits.test.index)


def test_targets_crossing_a_split_boundary_are_purged() -> None:
    frame = pd.DataFrame(
        {
            'demand_date': pd.to_datetime(
                ['2025-12-24', '2025-12-25', '2025-12-31']
            ),
            'target_demand_7d': [7.0, 7.0, 7.0],
        }
    )
    train = chronological_split(frame).train

    assert train.loc[train['demand_date'].eq('2025-12-24'), 'target_demand_7d'].notna().all()
    assert train.loc[train['demand_date'].gt('2025-12-24'), 'target_demand_7d'].isna().all()


def test_seasonal_baseline_metrics() -> None:
    frame = pd.DataFrame(
        {
            'target_demand_7d': [10.0, 20.0, 30.0],
            'rolling_sum_7d': [12.0, 18.0, 30.0],
        }
    )
    metrics = evaluate_baseline(frame)

    assert metrics.rows == 3
    assert round(metrics.mae, 6) == round(4 / 3, 6)
    assert round(metrics.bias, 6) == 0
    assert round(metrics.wape or 0, 6) == round(4 / 60, 6)
