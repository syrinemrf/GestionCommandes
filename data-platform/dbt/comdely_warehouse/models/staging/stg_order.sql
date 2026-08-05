select
    run.source_system::text as source_system,
    source.id::bigint as source_order_id,
    source.numero::bigint as order_number,
    source.date::timestamptz as ordered_at,
    source.total_ht::numeric(18, 3) as total_ht,
    source.taux_tva::numeric(8, 3) as tax_rate,
    round(
        source.total_ht::numeric * source.taux_tva::numeric / 100,
        3
    )::numeric(18, 3) as tax_amount,
    round(
        source.total_ht::numeric * (1 + source.taux_tva::numeric / 100),
        3
    )::numeric(18, 3) as total_ttc,
    source.statut::text as order_status,
    source.note::text as order_note,
    coalesce(source.is_deleted, false)::boolean as is_deleted,
    source.user_id::bigint as source_created_by_user_id,
    source.fournisseur_id::bigint as source_supplier_id,
    source.client_id::bigint as source_client_id,
    source.demo_batch::text as demo_batch,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'commande') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
