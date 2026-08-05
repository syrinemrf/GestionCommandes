select
    run.source_system::text as source_system,
    source.id::bigint as source_user_id,
    nullif(btrim(source.nom), '')::text as last_name,
    nullif(btrim(source.prenom), '')::text as first_name,
    lower(nullif(btrim(source.email), ''))::text as email,
    source.role::text as user_role,
    nullif(btrim(source.libelle), '')::text as supplier_label,
    coalesce(source.is_deleted, false)::boolean as is_deleted,
    source.demo_batch::text as demo_batch,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'user') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
