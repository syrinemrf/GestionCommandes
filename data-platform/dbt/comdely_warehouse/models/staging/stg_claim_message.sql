select
    run.source_system::text as source_system,
    source.id::bigint as source_claim_message_id,
    source.reclamation_id::bigint as source_claim_id,
    source.auteur_id::bigint as source_author_user_id,
    source.contenu::text as message,
    source.created_at::timestamptz as created_at,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'reclamation_message') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
