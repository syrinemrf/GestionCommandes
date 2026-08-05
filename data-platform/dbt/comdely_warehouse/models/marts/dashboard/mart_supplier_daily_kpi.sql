{{ config(materialized='table') }}

with date_bounds as (
    select
        min(ordered_at::date) as min_date,
        max(ordered_at::date) as max_date
    from {{ ref('stg_order') }}
),
supplier_dates as (
    select
        date_day.calendar_date,
        supplier.supplier_key,
        supplier.source_system,
        supplier.source_supplier_id,
        supplier.extracted_at as supplier_extracted_at
    from {{ ref('dim_supplier') }} as supplier
    cross join {{ ref('dim_date') }} as date_day
    cross join date_bounds
    where date_day.calendar_date between date_bounds.min_date and date_bounds.max_date
),
order_metrics as (
    select
        source_system,
        source_supplier_id,
        ordered_at::date as order_date,
        count(*)::bigint as order_count,
        count(*) filter (where order_status = 'ANNULEE')::bigint as cancelled_order_count,
        count(*) filter (where order_status <> 'ANNULEE')::bigint as revenue_order_count,
        coalesce(sum(total_ht) filter (where order_status <> 'ANNULEE'), 0)::numeric(18, 3) as revenue_ht,
        coalesce(sum(total_ttc) filter (where order_status <> 'ANNULEE'), 0)::numeric(18, 3) as revenue_ttc,
        max(extracted_at) as last_updated_at
    from {{ ref('stg_order') }}
    where not is_deleted
    group by source_system, source_supplier_id, ordered_at::date
),
unit_metrics as (
    select
        source_system,
        source_supplier_id,
        ordered_at::date as order_date,
        sum(quantity)::bigint as ordered_units,
        max(extracted_at) as last_updated_at
    from {{ ref('fact_order_line') }}
    where not order_is_deleted
      and current_order_status <> 'ANNULEE'
    group by source_system, source_supplier_id, ordered_at::date
)
select
    {{ stable_surrogate_key(['supplier_dates.source_system', 'supplier_dates.source_supplier_id', 'supplier_dates.calendar_date']) }} as supplier_daily_kpi_key,
    supplier_dates.calendar_date,
    to_char(supplier_dates.calendar_date, 'YYYYMMDD')::integer as date_key,
    supplier_dates.supplier_key,
    supplier_dates.source_system,
    supplier_dates.source_supplier_id,
    coalesce(order_metrics.order_count, 0)::bigint as order_count,
    coalesce(order_metrics.cancelled_order_count, 0)::bigint as cancelled_order_count,
    case
        when coalesce(order_metrics.order_count, 0) = 0 then 0::numeric(12, 6)
        else round(order_metrics.cancelled_order_count::numeric / order_metrics.order_count, 6)::numeric(12, 6)
    end as cancellation_rate,
    coalesce(order_metrics.revenue_ht, 0)::numeric(18, 3) as revenue_ht,
    coalesce(order_metrics.revenue_ttc, 0)::numeric(18, 3) as revenue_ttc,
    coalesce(unit_metrics.ordered_units, 0)::bigint as ordered_units,
    case
        when coalesce(order_metrics.revenue_order_count, 0) = 0 then 0::numeric(18, 3)
        else round(order_metrics.revenue_ht / order_metrics.revenue_order_count, 3)::numeric(18, 3)
    end as average_order_value_ht,
    case
        when coalesce(order_metrics.revenue_order_count, 0) = 0 then 0::numeric(18, 3)
        else round(order_metrics.revenue_ttc / order_metrics.revenue_order_count, 3)::numeric(18, 3)
    end as average_order_value_ttc,
    greatest(
        supplier_dates.supplier_extracted_at,
        order_metrics.last_updated_at,
        unit_metrics.last_updated_at
    ) as last_updated_at
from supplier_dates
left join order_metrics
    on order_metrics.source_system = supplier_dates.source_system
    and order_metrics.source_supplier_id = supplier_dates.source_supplier_id
    and order_metrics.order_date = supplier_dates.calendar_date
left join unit_metrics
    on unit_metrics.source_system = supplier_dates.source_system
    and unit_metrics.source_supplier_id = supplier_dates.source_supplier_id
    and unit_metrics.order_date = supplier_dates.calendar_date
