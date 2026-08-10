from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd


@dataclass(frozen=True, slots=True)
class BaselineMetrics:
    rows: int
    mae: float
    rmse: float
    wape: float | None
    bias: float

    def to_dict(self) -> dict[str, int | float | None]:
        return {
            'rows': self.rows,
            'mae': round(self.mae, 4),
            'rmse': round(self.rmse, 4),
            'wape': None if self.wape is None else round(self.wape, 4),
            'bias': round(self.bias, 4),
        }


def seasonal_baseline(frame: pd.DataFrame) -> pd.Series:
    '''Use the previous complete seven-day demand as the next-week forecast.'''
    return frame['rolling_sum_7d'].astype('float64')


def evaluate_baseline(frame: pd.DataFrame) -> BaselineMetrics:
    values = pd.DataFrame(
        {
            'actual': frame['target_demand_7d'],
            'prediction': seasonal_baseline(frame),
        }
    ).dropna()
    if values.empty:
        raise ValueError('No mature target is available for baseline evaluation')

    errors = values['prediction'] - values['actual']
    absolute_errors = errors.abs()
    actual_total = float(values['actual'].abs().sum())

    return BaselineMetrics(
        rows=len(values),
        mae=float(absolute_errors.mean()),
        rmse=float(np.sqrt(np.mean(np.square(errors)))),
        wape=None if actual_total == 0 else float(absolute_errors.sum() / actual_total),
        bias=float(errors.mean()),
    )
