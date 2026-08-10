from __future__ import annotations

import numpy as np
import pandas as pd


KEY = 'variation_key'
DATE = 'demand_date'
DEMAND = 'demand_quantity'
REQUIRED_COLUMNS = {
    DATE,
    KEY,
    'supplier_key',
    'product_key',
    'source_supplier_id',
    'source_product_id',
    'source_variation_id',
    'product_created_at',
    DEMAND,
}
LAGS = (1, 7, 14, 28)
WINDOWS = (7, 14, 28)


def _validate(frame: pd.DataFrame, today: pd.Timestamp) -> None:
    missing = REQUIRED_COLUMNS - set(frame.columns)
    if missing:
        raise ValueError(f'Missing required columns: {sorted(missing)}')
    if frame.duplicated([DATE, KEY]).any():
        raise ValueError('The daily demand grain must be unique by date and variation')
    if (frame[DEMAND] < 0).any():
        raise ValueError('Demand quantities must be non-negative')
    if (frame[DATE] > today).any():
        raise ValueError('The modeling dataset must not contain future dates')
    created_at = pd.to_datetime(frame['product_created_at']).dt.tz_localize(None).dt.normalize()
    if (frame[DATE] < created_at).any():
        raise ValueError('Demand rows cannot predate product creation')

    gaps = frame.groupby(KEY, sort=False)[DATE].diff().dropna()
    if not gaps.eq(pd.Timedelta(days=1)).all():
        raise ValueError('Each variation must contain a continuous daily date spine')


def _future_target(series: pd.Series) -> pd.Series:
    return (
        series.shift(-1)
        .rolling(7, min_periods=7)
        .sum()
        .shift(-6)
    )


def build_demand_features(
    frame: pd.DataFrame,
    *,
    today: pd.Timestamp | None = None,
) -> pd.DataFrame:
    '''Build demand features using only information strictly before each row.'''
    data = frame.copy()
    data[DATE] = pd.to_datetime(data[DATE]).dt.normalize()
    data[DEMAND] = pd.to_numeric(data[DEMAND], errors='raise').astype('int64')
    data = data.sort_values([KEY, DATE], kind='stable').reset_index(drop=True)
    cutoff = (
        pd.Timestamp.today().normalize()
        if today is None
        else pd.Timestamp(today).normalize()
    )
    _validate(data, cutoff)

    demand_by_variation = data.groupby(KEY, sort=False)[DEMAND]
    data['target_demand_7d'] = demand_by_variation.transform(_future_target)

    for lag in LAGS:
        data[f'demand_lag_{lag}'] = demand_by_variation.shift(lag)

    data['_past_demand'] = demand_by_variation.shift(1)
    past_by_variation = data.groupby(KEY, sort=False)['_past_demand']
    for window in WINDOWS:
        rolling = past_by_variation.rolling(window, min_periods=window)
        data[f'rolling_mean_{window}d'] = (
            rolling.mean().reset_index(level=0, drop=True)
        )
        data[f'rolling_sum_{window}d'] = (
            rolling.sum().reset_index(level=0, drop=True)
        )
        data[f'rolling_std_{window}d'] = (
            rolling.std(ddof=0).reset_index(level=0, drop=True)
        )

    data['recent_trend_7d_vs_28d'] = (
        data['rolling_mean_7d'] - data['rolling_mean_28d']
    )
    data = _add_calendar_features(data)
    data = _add_intermittence_features(data)

    return data.drop(columns=['_past_demand'])


def _add_calendar_features(data: pd.DataFrame) -> pd.DataFrame:
    data['day_of_week'] = data[DATE].dt.dayofweek.astype('int8')
    data['day_of_month'] = data[DATE].dt.day.astype('int8')
    data['week_of_year'] = data[DATE].dt.isocalendar().week.astype('int16')
    data['month'] = data[DATE].dt.month.astype('int8')
    data['quarter'] = data[DATE].dt.quarter.astype('int8')
    data['is_weekend'] = data['day_of_week'].isin([5, 6]).astype('int8')
    data['day_of_week_sin'] = np.sin(2 * np.pi * data['day_of_week'] / 7)
    data['day_of_week_cos'] = np.cos(2 * np.pi * data['day_of_week'] / 7)
    data['month_sin'] = np.sin(2 * np.pi * (data['month'] - 1) / 12)
    data['month_cos'] = np.cos(2 * np.pi * (data['month'] - 1) / 12)

    return data


def _zero_streak(past_demand: pd.Series) -> pd.Series:
    blocks = past_demand.ne(0).cumsum()
    return past_demand.eq(0).groupby(blocks).cumsum().astype('int32')


def _add_intermittence_features(data: pd.DataFrame) -> pd.DataFrame:
    past = data['_past_demand']
    data['was_zero_previous_day'] = (
        past.eq(0).where(past.notna()).astype('Int8')
    )
    data['zero_demand_streak'] = (
        data.groupby(KEY, sort=False)['_past_demand']
        .transform(_zero_streak)
        .astype('int32')
    )

    data['_past_nonzero'] = past.gt(0).where(past.notna()).astype('float64')
    nonzero_rolling = (
        data.groupby(KEY, sort=False)['_past_nonzero']
        .rolling(28, min_periods=28)
        .sum()
        .reset_index(level=0, drop=True)
    )
    data['nonzero_days_28d'] = nonzero_rolling
    data['zero_demand_rate_28d'] = 1 - (nonzero_rolling / 28)
    data['is_intermittent_28d'] = (
        data['zero_demand_rate_28d'].ge(0.75)
        .where(data['zero_demand_rate_28d'].notna())
        .astype('Int8')
    )

    data['_sale_date'] = data[DATE].where(data[DEMAND] > 0)
    data['_last_sale_date'] = (
        data.groupby(KEY, sort=False)['_sale_date']
        .transform(lambda series: series.shift(1).ffill())
    )
    data['days_since_last_sale'] = (
        data[DATE] - data['_last_sale_date']
    ).dt.days.astype('float64')

    return data.drop(
        columns=['_past_nonzero', '_sale_date', '_last_sale_date']
    )
