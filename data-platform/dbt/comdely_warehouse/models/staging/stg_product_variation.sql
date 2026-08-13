select
    run.source_system::text as source_system,
    source.id::bigint as source_variation_id,
    source.product_id::bigint as source_product_id,
    nullif(btrim(source.libelle), '')::text as variation_name,
    coalesce(source.attributs, '{}'::jsonb)::jsonb as attributes,
    source.prix_supplement::numeric(18, 3) as price_supplement_ht,
    source.stock::bigint as stock_initial,
    source.stock_utilise::bigint as stock_used,
    source.stock_reserve::bigint as stock_reserved,
    (source.stock - source.stock_utilise)::bigint as stock_physical,
    (source.stock - source.stock_utilise - source.stock_reserve)::bigint as stock_available,
    nullif(btrim(source.reference), '')::text as reference,
    coalesce(source.is_deleted, false)::boolean as is_deleted,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'product_variation') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
