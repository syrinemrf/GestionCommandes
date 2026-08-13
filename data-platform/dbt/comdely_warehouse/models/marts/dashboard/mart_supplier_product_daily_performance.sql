{{ config(materialized='table') }}

with daily_performance as (
    select
        lines.ordered_at::date as calendar_date,
        lines.supplier_key,
        lines.product_key,
        lines.source_system,
        lines.source_supplier_id,
        lines.source_product_id,
        sum(lines.quantity)::bigint as units_sold,
        sum(lines.line_total_ht)::numeric(18, 3) as revenue_ht,
        count(distinct lines.source_order_id)::bigint as order_count,
        max(lines.extracted_at) as last_updated_at
    from {{ ref('fact_order_line') }} as lines
    where not lines.order_is_deleted
      and lines.current_order_status <> 'ANNULEE'
    group by
        lines.ordered_at::date,
        lines.supplier_key,
        lines.product_key,
        lines.source_system,
        lines.source_supplier_id,
        lines.source_product_id
)
select
    {{ stable_surrogate_key(['performance.source_system', 'performance.source_supplier_id', 'performance.source_product_id', 'performance.calendar_date']) }} as supplier_product_daily_performance_key,
    performance.calendar_date,
    performance.supplier_key,
    performance.product_key,
    performance.source_system,
    performance.source_supplier_id,
    performance.source_product_id,
    product.product_name,
    product.is_deleted as product_is_deleted,
    performance.units_sold,
    performance.revenue_ht,
    performance.order_count,
    performance.last_updated_at
from daily_performance as performance
inner join {{ ref('dim_product') }} as product
    on product.product_key = performance.product_key
