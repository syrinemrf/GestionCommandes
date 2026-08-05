select
    run.source_system::text as source_system,
    source.id::bigint as source_client_id,
    source.fournisseur_id::bigint as source_supplier_id,
    nullif(btrim(source.nom), '')::text as last_name,
    nullif(btrim(source.prenom), '')::text as first_name,
    nullif(btrim(source.societe), '')::text as company_name,
    nullif(btrim(source.telephone), '')::text as phone,
    lower(nullif(btrim(source.email), ''))::text as email,
    nullif(btrim(source.rue), '')::text as street,
    nullif(btrim(source.complement_adresse), '')::text as address_complement,
    nullif(btrim(source.ville), '')::text as city,
    nullif(btrim(source.code_postal), '')::text as postal_code,
    coalesce(source.is_deleted, false)::boolean as is_deleted,
    source.demo_batch::text as demo_batch,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'client') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
