from __future__ import annotations

import numpy as np
import pandas as pd


def croston_sba(frame: pd.DataFrame, *, alpha: float = 0.2, horizon: int = 7) -> pd.Series:
    if not 0 < alpha <= 1:
        raise ValueError('alpha must be in (0, 1]')
    result = pd.Series(index=frame.index, dtype='float64')
    for _, group in frame.groupby('variation_key', sort=False):
        size, interval, elapsed = 0.0, 1.0, 1
        initialized = False
        for index, demand in zip(group.index, group['demand_quantity']):
            forecast = 0.0 if not initialized else (1 - alpha / 2) * size / interval
            result.at[index] = max(0.0, forecast * horizon)
            if demand > 0:
                if not initialized:
                    size, interval, initialized = float(demand), float(elapsed), True
                else:
                    size += alpha * (float(demand) - size)
                    interval += alpha * (elapsed - interval)
                elapsed = 1
            else:
                elapsed += 1
    return result


def croston_sba_next(demand: pd.Series, *, alpha: float = 0.2, horizon: int = 7) -> float:
    size, interval, elapsed = 0.0, 1.0, 1
    initialized = False
    for value in demand:
        if value > 0:
            if not initialized:
                size, interval, initialized = float(value), float(elapsed), True
            else:
                size += alpha * (float(value) - size)
                interval += alpha * (elapsed - interval)
            elapsed = 1
        else:
            elapsed += 1
    if not initialized:
        return 0.0
    return max(0.0, (1 - alpha / 2) * size / interval * horizon)
