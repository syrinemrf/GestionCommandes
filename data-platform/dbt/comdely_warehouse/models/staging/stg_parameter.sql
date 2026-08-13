select
    run.source_system::text as source_system,
    source.id::bigint as source_parameter_id,
    source.numero_commande::bigint as next_order_number,
    source.tva::numeric(8, 3) as current_tax_rate,
    source.extracted_at::timestamptz as extracted_at,
    source.etl_run_id::uuid as etl_run_id
from {{ source('raw', 'parametre') }} as source
inner join {{ source('meta', 'etl_run') }} as run
    on run.id = source.etl_run_id
