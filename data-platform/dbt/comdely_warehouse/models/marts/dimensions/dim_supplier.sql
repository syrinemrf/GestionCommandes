select
    {{ stable_surrogate_key(['source_system', 'source_user_id']) }} as supplier_key,
    source_system,
    source_user_id as source_supplier_id,
    supplier_label,
    first_name,
    last_name,
    email,
    is_deleted,
    demo_batch,
    extracted_at
from {{ ref('stg_user') }}
where user_role = 'ROLE_FOURNISSEUR'
