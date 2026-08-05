select
    run.source_system::text as source_system,
    source.id::bigint as source_order_line_id,
    source.commande_id::bigint as source_order_id,
    source.produit_id::bigint as source_product_id,
    source.variation_id::bigint as source_variation_id,
    nullif(btrim(source.nom_produit), '')::text as historical_product_name,
    nullif(btrim(source.nom_variation), '')::text as historical_variation_name,
    source.quantite::bigint as quantity,
    source.prix_unitaire::numeric(18, 3) as historical_unit_price_ht,
    round(
        source.prix_unitaire::numeric * source.quantite::numeric,
        3
    )::numeric(18, 3) as line_total_ht,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'ligne_commande') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
