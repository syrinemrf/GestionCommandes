{{ config(materialized='table') }}

with latest_prediction as (
    select *
    from (
        select
            prediction.*,
            row_number() over (
                partition by prediction.source_system, prediction.source_variation_id
                order by prediction.prediction_date desc, prediction.predicted_at desc
            ) as prediction_rank
        from {{ source('ml', 'stock_prediction') }} as prediction
    ) as ranked
    where prediction_rank = 1
)
select
    {{ stable_surrogate_key(['prediction.source_system', 'prediction.source_variation_id']) }} as supplier_stock_risk_key,
    variation.supplier_key,
    variation.product_key,
    variation.variation_key,
    prediction.source_system,
    prediction.source_supplier_id,
    prediction.source_product_id,
    prediction.source_variation_id,
    product.product_name,
    variation.variation_name,
    prediction.prediction_date,
    prediction.predicted_at,
    prediction.forecast_central_7d,
    prediction.forecast_q90_7d,
    prediction.stock_available,
    prediction.risk,
    prediction.recommended_quantity,
    prediction.model_version,
    prediction.business_explanation,
    prediction.shap_factors,
    coalesce(history.demand_history, jsonb_build_array()) as demand_history,
    prediction.predicted_at as last_updated_at
from latest_prediction as prediction
inner join {{ ref('dim_variation') }} as variation
    on variation.source_system = prediction.source_system
    and variation.source_variation_id = prediction.source_variation_id
    and variation.source_supplier_id = prediction.source_supplier_id
inner join {{ ref('dim_product') }} as product
    on product.product_key = variation.product_key
left join lateral (
    select jsonb_agg(
        jsonb_build_object(
            'date', demand.demand_date,
            'quantity', demand.demand_quantity
        ) order by demand.demand_date
    ) as demand_history
    from {{ ref('mart_ml_daily_demand') }} as demand
    where demand.source_system = prediction.source_system
      and demand.source_supplier_id = prediction.source_supplier_id
      and demand.source_variation_id = prediction.source_variation_id
      and demand.demand_date between prediction.prediction_date - 13 and prediction.prediction_date
) as history on true
