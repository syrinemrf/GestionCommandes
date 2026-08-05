select
    {{ stable_surrogate_key(['source_system', 'source_client_id']) }} as client_key,
    {{ stable_surrogate_key(['source_system', 'source_supplier_id']) }} as supplier_key,
    source_system,
    source_client_id,
    source_supplier_id,
    company_name,
    city,
    postal_code,
    is_deleted,
    demo_batch,
    extracted_at
from {{ ref('stg_client') }}
