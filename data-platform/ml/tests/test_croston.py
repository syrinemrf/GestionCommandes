import pandas as pd

from comdely_ml.croston import croston_sba


def test_croston_is_reproducible_and_uses_only_past_rows() -> None:
    frame = pd.DataFrame({'variation_key': ['v'] * 5, 'demand_quantity': [0, 4, 0, 0, 2]})
    first = croston_sba(frame, alpha=.2)
    changed = frame.copy()
    changed.loc[4, 'demand_quantity'] = 100
    second = croston_sba(changed, alpha=.2)
    pd.testing.assert_series_equal(first.iloc[:5], second.iloc[:5])
