begin;

alter table ml.stock_prediction
    add column if not exists business_explanation jsonb,
    add column if not exists shap_factors jsonb;

update ml.stock_prediction
set business_explanation = jsonb_build_object(
    'stock_available', stock_available,
    'forecast_central_7d', forecast_central_7d,
    'forecast_q90_7d', forecast_q90_7d,
    'deficit', greatest(0, ceil(forecast_central_7d - stock_available)),
    'risk', risk,
    'recommended_quantity', recommended_quantity
)
where business_explanation is null;

alter table ml.stock_prediction
    alter column business_explanation set not null;

do $$
begin
    if not exists (
        select 1
        from pg_constraint
        where conname = 'stock_prediction_business_explanation_object_check'
          and conrelid = 'ml.stock_prediction'::regclass
    ) then
        alter table ml.stock_prediction
            add constraint stock_prediction_business_explanation_object_check
            check (jsonb_typeof(business_explanation) = 'object');
    end if;

    if not exists (
        select 1
        from pg_constraint
        where conname = 'stock_prediction_shap_factors_array_check'
          and conrelid = 'ml.stock_prediction'::regclass
    ) then
        alter table ml.stock_prediction
            add constraint stock_prediction_shap_factors_array_check
            check (shap_factors is null or jsonb_typeof(shap_factors) = 'array');
    end if;
end
$$;

comment on column ml.stock_prediction.business_explanation is
    'Deterministic stock decision inputs and outputs; deficit is measured against the central forecast.';
comment on column ml.stock_prediction.shap_factors is
    'Top three offline SHAP contributions to the q90 tree forecast; null when unavailable.';

commit;
