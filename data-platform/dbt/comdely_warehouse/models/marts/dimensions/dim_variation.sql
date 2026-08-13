select
    {{ stable_surrogate_key(['variation.source_system', 'variation.source_variation_id']) }} as variation_key,
    {{ stable_surrogate_key(['variation.source_system', 'variation.source_product_id']) }} as product_key,
    {{ stable_surrogate_key(['product.source_system', 'product.source_supplier_id']) }} as supplier_key,
    variation.source_system,
    variation.source_variation_id,
    variation.source_product_id,
    product.source_supplier_id,
    variation.variation_name,
    variation.attributes,
    variation.reference,
    variation.price_supplement_ht,
    product.base_price_ht,
    (product.base_price_ht + variation.price_supplement_ht)::numeric(18, 3) as current_unit_price_ht,
    variation.stock_initial,
    variation.stock_used,
    variation.stock_reserved,
    variation.stock_physical,
    variation.stock_available,
    variation.is_deleted,
    variation.extracted_at
from {{ ref('stg_product_variation') }} as variation
left join {{ ref('stg_product') }} as product
    on product.source_system = variation.source_system
    and product.source_product_id = variation.source_product_id
