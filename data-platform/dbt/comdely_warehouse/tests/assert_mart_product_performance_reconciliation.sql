with expected as (
    select
        source_system,
        source_supplier_id,
        date_trunc('month', ordered_at)::date as period_start,
        sum(quantity)::bigint as units_sold,
        sum(line_total_ht)::numeric(18, 3) as revenue_ht
    from {{ ref('fact_order_line') }}
    where not order_is_deleted
      and current_order_status <> 'ANNULEE'
    group by source_system, source_supplier_id, date_trunc('month', ordered_at)::date
),
actual as (
    select
        source_system,
        source_supplier_id,
        period_start,
        sum(units_sold)::bigint as units_sold,
        sum(revenue_ht)::numeric(18, 3) as revenue_ht
    from {{ ref('mart_supplier_product_performance') }}
    group by source_system, source_supplier_id, period_start
)
select
    coalesce(expected.source_system, actual.source_system) as source_system,
    coalesce(expected.source_supplier_id, actual.source_supplier_id) as source_supplier_id,
    coalesce(expected.period_start, actual.period_start) as period_start
from expected
full outer join actual
    on actual.source_system = expected.source_system
    and actual.source_supplier_id = expected.source_supplier_id
    and actual.period_start = expected.period_start
where expected.source_supplier_id is null
   or actual.source_supplier_id is null
   or expected.units_sold <> actual.units_sold
   or expected.revenue_ht <> actual.revenue_ht
