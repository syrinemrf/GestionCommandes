select risk.source_variation_id, risk.source_supplier_id, variation.source_supplier_id as expected_supplier_id
from {{ ref('mart_supplier_stock_risk') }} as risk
inner join {{ ref('dim_variation') }} as variation
    on variation.variation_key = risk.variation_key
where risk.source_supplier_id <> variation.source_supplier_id
