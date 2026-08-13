from __future__ import annotations

from dataclasses import dataclass

import pandas as pd


TRAIN_START = pd.Timestamp('2024-08-04')
TRAIN_END = pd.Timestamp('2025-12-31')
VALIDATION_START = pd.Timestamp('2026-01-01')
VALIDATION_END = pd.Timestamp('2026-05-31')
TEST_START = pd.Timestamp('2026-06-01')
TEST_END = pd.Timestamp('2026-08-04')


@dataclass(frozen=True, slots=True)
class DatasetSplits:
    train: pd.DataFrame
    validation: pd.DataFrame
    test: pd.DataFrame


def _between(
    frame: pd.DataFrame,
    start: pd.Timestamp,
    end: pd.Timestamp,
) -> pd.DataFrame:
    result = frame.loc[frame['demand_date'].between(start, end)].copy()
    if 'target_demand_7d' in result.columns:
        crosses_boundary = (
            result['demand_date'] + pd.Timedelta(days=7) > end
        )
        result.loc[crosses_boundary, 'target_demand_7d'] = float('nan')

    return result


def chronological_split(frame: pd.DataFrame) -> DatasetSplits:
    return DatasetSplits(
        train=_between(frame, TRAIN_START, TRAIN_END),
        validation=_between(frame, VALIDATION_START, VALIDATION_END),
        test=_between(frame, TEST_START, TEST_END),
    )
