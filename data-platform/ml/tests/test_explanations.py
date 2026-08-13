import numpy as np
import pandas as pd

from comdely_ml.explanations import business_explanation, tree_pipeline_explanations
from comdely_ml.modeling import FEATURES, build_pipeline


def _training_frame(rows: int = 48) -> pd.DataFrame:
    rng = np.random.default_rng(17)
    frame = pd.DataFrame({feature: rng.normal(size=rows) for feature in FEATURES})
    frame['source_supplier_id'] = rng.choice([1, 2], size=rows)
    frame['source_product_id'] = rng.choice([10, 20, 30], size=rows)
    frame['source_variation_id'] = rng.choice([100, 200, 300, 400], size=rows)
    return frame


def test_business_explanation_is_deterministic_and_non_negative() -> None:
    explanation = business_explanation(
        stock_available=4,
        forecast_central_7d=9.2,
        forecast_q90_7d=12.8,
        risk='HIGH',
        recommended_quantity=9,
    )

    assert explanation == {
        'stock_available': 4,
        'forecast_central_7d': 9.2,
        'forecast_q90_7d': 12.8,
        'deficit': 6,
        'risk': 'HIGH',
        'recommended_quantity': 9,
    }


def test_shap_count_and_names_match_pipeline_features() -> None:
    frame = _training_frame()
    pipeline = build_pipeline(seed=17, loss='quantile', quantile=.9, max_iter=5)
    pipeline.fit(frame[FEATURES], np.arange(len(frame), dtype=float))

    explanations = tree_pipeline_explanations(pipeline, frame.iloc[:2], FEATURES)

    assert len(explanations) == 2
    assert all(len(factors) == 3 for factors in explanations)
    assert all(factor['name'] in FEATURES for factors in explanations for factor in factors)
    assert all(factor['direction'] in {'INCREASES', 'DECREASES', 'NEUTRAL'} for factors in explanations for factor in factors)
