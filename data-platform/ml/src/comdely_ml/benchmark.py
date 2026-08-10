from __future__ import annotations

import argparse
import json
from pathlib import Path

import joblib
import numpy as np
import pandas as pd

from .baseline import seasonal_baseline
from .config import Settings
from .croston import croston_sba
from .features import build_demand_features
from .loader import load_daily_demand
from .metrics import forecast_metrics
from .modeling import FEATURES, build_pipeline
from .splits import chronological_split


def _mature(frame: pd.DataFrame) -> pd.DataFrame:
    return frame.loc[frame['target_demand_7d'].notna()].copy()


def _groups(frame: pd.DataFrame, prediction: np.ndarray) -> dict[str, list[dict[str, object]]]:
    data = frame[['source_supplier_id', 'source_variation_id', 'target_demand_7d', 'zero_demand_rate_28d']].copy()
    data['prediction'] = prediction
    data['intermittency_level'] = pd.cut(data['zero_demand_rate_28d'], [-np.inf, .5, .75, np.inf], labels=['faible', 'moyenne', 'forte'])
    output: dict[str, list[dict[str, object]]] = {}
    for label, column in [('supplier', 'source_supplier_id'), ('variation', 'source_variation_id'), ('intermittency', 'intermittency_level')]:
        rows = []
        for value, group in data.groupby(column, observed=True):
            valid = group[['target_demand_7d', 'prediction']].dropna()
            if valid.empty:
                continue
            rows.append({'group': str(value), **forecast_metrics(valid['target_demand_7d'], valid['prediction'])})
        output[label] = rows
    return output


def _evaluate(frame: pd.DataFrame, prediction: np.ndarray | pd.Series) -> dict[str, object]:
    values = np.clip(np.asarray(prediction, dtype=float), 0, None)
    return {'overall': forecast_metrics(frame['target_demand_7d'], values), 'by_group': _groups(frame, values)}


def run(*, seed: int, artifact_dir: Path, result_path: Path, report_path: Path) -> dict[str, object]:
    prepared = build_demand_features(load_daily_demand(Settings.from_env()))
    split = chronological_split(prepared)
    train, validation, test = map(_mature, (split.train, split.validation, split.test))

    baseline_validation = seasonal_baseline(validation)
    baseline_test = seasonal_baseline(test)

    full_sequence = prepared.sort_values(['variation_key', 'demand_date'], kind='stable')
    croston_candidates: dict[str, dict[str, object]] = {}
    croston_all: dict[float, pd.Series] = {}
    for alpha in (0.1, 0.2, 0.3):
        prediction = croston_sba(full_sequence, alpha=alpha)
        croston_all[alpha] = prediction
        selected = prediction.reindex(validation.index)
        croston_candidates[str(alpha)] = forecast_metrics(validation['target_demand_7d'], selected)
    best_alpha = min((0.1, 0.2, 0.3), key=lambda value: croston_candidates[str(value)]['wape'] or float('inf'))

    candidates = [
        {'max_leaf_nodes': 15, 'l2_regularization': 1.0},
        {'max_leaf_nodes': 31, 'l2_regularization': 2.0},
    ]
    validation_candidates = []
    for params in candidates:
        pipeline = build_pipeline(seed=seed, **params)
        pipeline.fit(train[FEATURES], train['target_demand_7d'])
        metrics = forecast_metrics(validation['target_demand_7d'], pipeline.predict(validation[FEATURES]))
        validation_candidates.append({'parameters': params, 'metrics': metrics})
    best = min(validation_candidates, key=lambda item: item['metrics']['wape'] or float('inf'))

    development = pd.concat([train, validation], ignore_index=True)
    point_model = build_pipeline(seed=seed, **best['parameters'])
    quantile_model = build_pipeline(seed=seed, loss='quantile', quantile=.9, **best['parameters'])
    point_model.fit(development[FEATURES], development['target_demand_7d'])
    quantile_model.fit(development[FEATURES], development['target_demand_7d'])
    point_prediction = np.clip(point_model.predict(test[FEATURES]), 0, None)
    q90_prediction = np.clip(quantile_model.predict(test[FEATURES]), 0, None)

    artifact_dir.mkdir(parents=True, exist_ok=True)
    joblib.dump(point_model, artifact_dir / 'hist_gradient_boosting_point.joblib')
    joblib.dump(quantile_model, artifact_dir / 'hist_gradient_boosting_q90.joblib')

    croston_test = croston_all[best_alpha].reindex(test.index)
    results: dict[str, object] = {
        'seed': seed,
        'selection_policy': 'Hyperparameters selected on validation only; final test evaluated once after refit on train+validation.',
        'dataset': {'train_rows': len(train), 'validation_rows': len(validation), 'test_rows': len(test), 'test_start': str(test['demand_date'].min().date()), 'test_end': str(test['demand_date'].max().date())},
        'validation': {'baseline': forecast_metrics(validation['target_demand_7d'], baseline_validation), 'croston_candidates': croston_candidates, 'hist_gradient_boosting_candidates': validation_candidates, 'selected_croston_alpha': best_alpha, 'selected_hgb_parameters': best['parameters']},
        'test': {
            'seasonal_baseline': _evaluate(test, baseline_test),
            'croston_sba': _evaluate(test, croston_test),
            'hist_gradient_boosting_point': _evaluate(test, point_prediction),
            'hist_gradient_boosting_q90': _evaluate(test, q90_prediction),
        },
        'warning': 'Results on synthetic data do not guarantee performance on real demand.',
    }
    result_path.parent.mkdir(parents=True, exist_ok=True)
    result_path.write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding='utf-8')
    _write_report(results, report_path)
    return results


def _write_report(results: dict[str, object], path: Path) -> None:
    test = results['test']
    lines = ['# Comparaison des modèles de demande', '', 'Évaluation chronologique. Les paramètres sont choisis sur la validation uniquement; le test final reste hors sélection.', '', '| Approche | MAE | WAPE | Pinball q90 | Ruptures simulées | Unités manquantes | Surstock | Qté moyenne |', '|---|---:|---:|---:|---:|---:|---:|---:|']
    for name, payload in test.items():
        metric = payload['overall']
        lines.append(f"| {name} | {metric['mae']} | {metric['wape']} | {metric['pinball_loss_q90']} | {metric['simulated_stockout_rate']} | {metric['missing_units']} | {metric['simulated_overstock_units']} | {metric['average_recommended_quantity']} |")
    baseline = test['seasonal_baseline']['overall']['wape']
    croston = test['croston_sba']['overall']['wape']
    point = test['hist_gradient_boosting_point']['overall']['wape']
    if point < baseline and point < croston:
        conclusion = 'Le modèle global apporte le meilleur WAPE et son gain est défendable sur ce test.'
    elif croston <= point:
        conclusion = "Croston-SBA obtient le meilleur WAPE. Le modèle global améliore la baseline mais ne justifie pas sa complexité supplémentaire; Croston-SBA est retenu à ce stade."
    else:
        conclusion = "Le modèle global ne surpasse pas la baseline en WAPE; il ne doit pas être retenu sans justification supplémentaire."
    lines += ['', '## Décision', '', conclusion, '', 'Les comparaisons détaillées par fournisseur, variation et intermittence sont conservées dans le JSON de résultats.', '', '> Ces données sont synthétiques : leurs performances ne garantissent pas celles de futures données réelles.', '']
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text('\n'.join(lines), encoding='utf-8')


def main() -> int:
    parser = argparse.ArgumentParser(description='Benchmark reproducible demand forecasting models')
    parser.add_argument('--seed', type=int, default=20260804)
    parser.add_argument('--artifacts', type=Path, default=Path('artifacts'))
    parser.add_argument('--results', type=Path, default=Path('artifacts/model_results.json'))
    parser.add_argument('--report', type=Path, default=Path('reports/model_comparison.md'))
    args = parser.parse_args()
    try:
        print(json.dumps(run(seed=args.seed, artifact_dir=args.artifacts, result_path=args.results, report_path=args.report), indent=2, ensure_ascii=False))
    except Exception as error:
        print(json.dumps({'status': 'error', 'message': str(error)}))
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
