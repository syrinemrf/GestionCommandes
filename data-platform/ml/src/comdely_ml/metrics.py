from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.metrics import mean_pinball_loss


def forecast_metrics(actual: pd.Series, prediction: np.ndarray | pd.Series, *, quantile: float = 0.9) -> dict[str, float | int | None]:
    y = np.asarray(actual, dtype=float)
    p = np.clip(np.asarray(prediction, dtype=float), 0, None)
    valid = np.isfinite(y) & np.isfinite(p)
    y, p = y[valid], p[valid]
    if not len(y):
        raise ValueError('No valid values to evaluate')
    recommended = np.ceil(p)
    missing = np.maximum(y - recommended, 0)
    overstock = np.maximum(recommended - y, 0)
    denominator = np.abs(y).sum()
    return {
        'rows': int(len(y)),
        'mae': round(float(np.abs(y - p).mean()), 4),
        'wape': None if denominator == 0 else round(float(np.abs(y - p).sum() / denominator), 4),
        'pinball_loss_q90': round(float(mean_pinball_loss(y, p, alpha=quantile)), 4),
        'simulated_stockout_rate': round(float((missing > 0).mean()), 4),
        'missing_units': round(float(missing.sum()), 3),
        'simulated_overstock_units': round(float(overstock.sum()), 3),
        'average_recommended_quantity': round(float(recommended.mean()), 4),
    }
