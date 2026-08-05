with expected as (
    select
        source_system,
        source_supplier_id,
        ordered_at::date as calendar_date,
        count(*)::bigint as order_count,
        count(*) filter (where order_status = 'ANNULEE')::bigint as cancelled_order_count,
        coalesce(sum(total_ht) filter (where order_status <> 'ANNULEE'), 0)::numeric(18, 3) as revenue_ht,
        coalesce(sum(total_ttc) filter (where order_status <> 'ANNULEE'), 0)::numeric(18, 3) as revenue_ttc
    from {{ ref('stg_order') }}
    where not is_deleted
    group by source_system, source_supplier_id, ordered_at::date
),
actual as (
    select *
    from {{ ref('mart_supplier_daily_kpi') }}
    where order_count > 0
)
select
    coalesce(expected.source_system, actual.source_system) as source_system,
    coalesce(expected.source_supplier_id, actual.source_supplier_id) as source_supplier_id,
    coalesce(expected.calendar_date, actual.calendar_date) as calendar_date
from expected
full outer join actual
    on actual.source_system = expected.source_system
    and actual.source_supplier_id = expected.source_supplier_id
    and actual.calendar_date = expected.calendar_date
where expected.source_supplier_id is null
   or actual.source_supplier_id is null
   or expected.order_count <> actual.order_count
   or expected.cancelled_order_count <> actual.cancelled_order_count
   or expected.revenue_ht <> actual.revenue_ht
   or expected.revenue_ttc <> actual.revenue_ttc
   or actual.cancelled_order_count > actual.order_count
