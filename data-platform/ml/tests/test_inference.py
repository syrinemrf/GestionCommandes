from comdely_ml.inference import stock_decision


def test_risk_levels_and_recommendation() -> None:
    assert stock_decision(10, 15, 8, sufficient_data=True)[:2] == ('HIGH', 7)
    assert stock_decision(10, 15, 12, sufficient_data=True)[:2] == ('MEDIUM', 3)
    assert stock_decision(10, 15, 20, sufficient_data=True)[:2] == ('LOW', 0)


def test_cold_start_and_negative_values_are_safe() -> None:
    assert stock_decision(10, 15, -3, sufficient_data=False) == ('INSUFFICIENT_DATA', 0, 0.0, 0.0, 0)
    assert stock_decision(-2, -1, 0, sufficient_data=True) == ('LOW', 0, 0.0, 0.0, 0)
