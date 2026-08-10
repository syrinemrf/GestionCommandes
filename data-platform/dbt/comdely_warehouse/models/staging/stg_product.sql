select
    run.source_system::text as source_system,
    source.id::bigint as source_product_id,
    source.id_fournisseur_id::bigint as source_supplier_id,
    nullif(btrim(source.libelle), '')::text as product_name,
    nullif(btrim(source.description), '')::text as product_description,
    nullif(btrim(source.image), '')::text as image_path,
    source.prix::numeric(18, 3) as base_price_ht,
    coalesce(source.is_deleted, false)::boolean as is_deleted,
    source.demo_batch::text as demo_batch,
    source.created_at::timestamptz as product_created_at,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'product') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
