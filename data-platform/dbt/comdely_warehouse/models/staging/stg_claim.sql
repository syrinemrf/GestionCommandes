select
    run.source_system::text as source_system,
    source.id::bigint as source_claim_id,
    source.fournisseur_id::bigint as source_supplier_id,
    source.admin_assigne_id::bigint as source_assigned_admin_id,
    nullif(btrim(source.objet), '')::text as subject,
    source.description::text as description,
    source.categorie::text as category,
    source.priorite::text as priority,
    source.statut::text as claim_status,
    source.created_at::timestamptz as created_at,
    source.updated_at::timestamptz as updated_at,
    source.resolved_at::timestamptz as resolved_at,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'reclamation') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
