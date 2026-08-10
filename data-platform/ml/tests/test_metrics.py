import numpy as np
import pandas as pd

from comdely_ml.metrics import forecast_metrics


def test_metrics_have_expected_simulation_values() -> None:
    result = forecast_metrics(pd.Series([2, 0, 5]), np.array([1.2, 1.1, 6.0]))
    assert result['mae'] == 0.9667
    assert result['missing_units'] == 0.0
    assert result['simulated_overstock_units'] == 3.0
    assert result['average_recommended_quantity'] == 3.3333


def test_wape_is_none_when_actual_total_is_zero() -> None:
    assert forecast_metrics(pd.Series([0, 0]), np.array([0, 1]))['wape'] is None
