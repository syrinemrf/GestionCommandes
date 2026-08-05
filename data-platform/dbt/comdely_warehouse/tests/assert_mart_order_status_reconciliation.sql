with expected as (
    select source_system, source_supplier_id, order_status, count(*)::bigint as order_count
    from {{ ref('stg_order') }}
    where not is_deleted
    group by source_system, source_supplier_id, order_status
)
select mart.*
from {{ ref('mart_supplier_order_status') }} as mart
left join expected
    on expected.source_system = mart.source_system
    and expected.source_supplier_id = mart.source_supplier_id
    and expected.order_status = mart.order_status
where mart.current_order_count <> coalesce(expected.order_count, 0)
   or mart.current_order_share < 0
   or mart.current_order_share > 1
