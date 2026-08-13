select
    run.source_system::text as source_system,
    source.id::bigint as source_status_history_id,
    source.commande_id::bigint as source_order_id,
    source.changed_by_id::bigint as source_changed_by_user_id,
    source.ancien_statut::text as previous_status,
    source.nouveau_statut::text as new_status,
    source.changed_at::timestamptz as changed_at,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'historique_statut_commande') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
