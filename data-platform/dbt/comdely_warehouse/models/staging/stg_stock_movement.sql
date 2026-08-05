select
    run.source_system::text as source_system,
    source.id::bigint as source_stock_movement_id,
    source.variation_id::bigint as source_variation_id,
    source.commande_id::bigint as source_order_id,
    source.created_by_id::bigint as source_created_by_user_id,
    source.type::text as movement_type,
    source.quantite::bigint as quantity,
    source.stock_avant::bigint as stock_physical_before,
    source.stock_apres::bigint as stock_physical_after,
    source.stock_reserve_avant::bigint as stock_reserved_before,
    source.stock_reserve_apres::bigint as stock_reserved_after,
    source.created_at::timestamptz as occurred_at,
    source.commentaire::text as comment,
    source.demo_batch::text as demo_batch,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'mouvement_stock') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
