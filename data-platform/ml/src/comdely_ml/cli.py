from __future__ import annotations

import argparse
import json
from pathlib import Path

from .baseline import evaluate_baseline
from .config import Settings
from .features import build_demand_features
from .loader import load_daily_demand
from .splits import chronological_split


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description='Prepare the leakage-safe Comdely demand dataset.'
    )
    parser.add_argument(
        '--output',
        type=Path,
        help='Optional CSV destination for the prepared dataset.',
    )
    return parser


def run(output: Path | None = None) -> dict[str, object]:
    settings = Settings.from_env()
    demand = load_daily_demand(settings)
    prepared = build_demand_features(demand)
    splits = chronological_split(prepared)

    if output is not None:
        output.parent.mkdir(parents=True, exist_ok=True)
        prepared.to_csv(output, index=False)

    summary: dict[str, object] = {
        'dataset': {
            'rows': len(prepared),
            'columns': len(prepared.columns),
            'variations': int(prepared['variation_key'].nunique()),
            'date_min': prepared['demand_date'].min().date().isoformat(),
            'date_max': prepared['demand_date'].max().date().isoformat(),
            'zero_demand_rate': round(
                float(prepared['demand_quantity'].eq(0).mean()),
                6,
            ),
            'mature_target_rows': int(
                prepared['target_demand_7d'].notna().sum()
            ),
        },
        'splits': {
            name: {
                'rows': len(frame),
                'mature_target_rows': int(
                    frame['target_demand_7d'].notna().sum()
                ),
            }
            for name, frame in (
                ('train', splits.train),
                ('validation', splits.validation),
                ('test', splits.test),
            )
        },
        'seasonal_baseline': {
            'validation': evaluate_baseline(splits.validation).to_dict(),
            'test': evaluate_baseline(splits.test).to_dict(),
        },
        'output': None if output is None else str(output),
    }
    return summary


def main() -> int:
    arguments = _parser().parse_args()
    try:
        summary = run(arguments.output)
    except Exception as error:
        print(json.dumps({'status': 'error', 'message': str(error)}))
        return 1

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    return 0
