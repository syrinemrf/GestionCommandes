select *
from {{ ref('mart_supplier_stock_risk') }}
where jsonb_typeof(business_explanation) <> 'object'
   or jsonb_typeof(demand_history) <> 'array'
   or jsonb_array_length(demand_history) > 14
   or (
       shap_factors is not null
       and (
           jsonb_typeof(shap_factors) <> 'array'
           or jsonb_array_length(shap_factors) > 3
           or exists (
               select 1
               from jsonb_array_elements(shap_factors) as factor
               where not factor ?& array['name', 'value', 'contribution', 'direction']
                  or factor->>'direction' not in ('INCREASES', 'DECREASES', 'NEUTRAL')
           )
       )
   )
