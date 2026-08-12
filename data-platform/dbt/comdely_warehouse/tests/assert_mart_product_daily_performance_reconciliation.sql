with expected as (
    select
        source_system,
        source_supplier_id,
        source_product_id,
        ordered_at::date as calendar_date,
        sum(quantity)::bigint as units_sold,
        sum(line_total_ht)::numeric(18, 3) as revenue_ht,
        count(distinct source_order_id)::bigint as order_count
    from {{ ref('fact_order_line') }}
    where not order_is_deleted
      and current_order_status <> 'ANNULEE'
    group by
        source_system,
        source_supplier_id,
        source_product_id,
        ordered_at::date
),
actual as (
    select
        source_system,
        source_supplier_id,
        source_product_id,
        calendar_date,
        units_sold,
        revenue_ht,
        order_count
    from {{ ref('mart_supplier_product_daily_performance') }}
)
select
    coalesce(expected.source_system, actual.source_system) as source_system,
    coalesce(expected.source_supplier_id, actual.source_supplier_id)
        as source_supplier_id,
    coalesce(expected.source_product_id, actual.source_product_id)
        as source_product_id,
    coalesce(expected.calendar_date, actual.calendar_date) as calendar_date
from expected
full outer join actual
    on actual.source_system = expected.source_system
    and actual.source_supplier_id = expected.source_supplier_id
    and actual.source_product_id = expected.source_product_id
    and actual.calendar_date = expected.calendar_date
where expected.source_product_id is null
   or actual.source_product_id is null
   or expected.units_sold <> actual.units_sold
   or expected.revenue_ht <> actual.revenue_ht
   or expected.order_count <> actual.order_count
