select
    {{ stable_surrogate_key(['source_system', 'source_product_id']) }} as product_key,
    {{ stable_surrogate_key(['source_system', 'source_supplier_id']) }} as supplier_key,
    source_system,
    source_product_id,
    source_supplier_id,
    product_name,
    product_description,
    image_path,
    base_price_ht,
    is_deleted,
    demo_batch,
    product_created_at,
    extracted_at
from {{ ref('stg_product') }}
