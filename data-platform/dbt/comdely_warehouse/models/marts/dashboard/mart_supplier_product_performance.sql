{{ config(materialized='table') }}

with eligible_lines as (
    select *
    from {{ ref('fact_order_line') }}
    where not order_is_deleted
      and current_order_status <> 'ANNULEE'
),
variation_performance as (
    select
        date_trunc('month', ordered_at)::date as period_start,
        supplier_key,
        product_key,
        variation_key,
        source_system,
        source_supplier_id,
        source_product_id,
        source_variation_id,
        sum(quantity)::bigint as units_sold,
        sum(line_total_ht)::numeric(18, 3) as revenue_ht,
        sum(line_total_ttc)::numeric(18, 3) as revenue_ttc,
        count(distinct source_order_id)::bigint as order_count,
        max(extracted_at) as last_updated_at
    from eligible_lines
    group by
        date_trunc('month', ordered_at)::date,
        supplier_key,
        product_key,
        variation_key,
        source_system,
        source_supplier_id,
        source_product_id,
        source_variation_id
),
product_performance as (
    select
        period_start,
        source_system,
        source_supplier_id,
        source_product_id,
        sum(units_sold)::bigint as product_units_sold,
        sum(revenue_ht)::numeric(18, 3) as product_revenue_ht
    from variation_performance
    group by period_start, source_system, source_supplier_id, source_product_id
),
ranked_products as (
    select
        *,
        dense_rank() over (
            partition by period_start, source_system, source_supplier_id
            order by product_revenue_ht desc, product_units_sold desc, source_product_id
        )::integer as product_rank
    from product_performance
)
select
    {{ stable_surrogate_key(['performance.source_system', 'performance.source_supplier_id', 'performance.source_product_id', 'performance.source_variation_id', 'performance.period_start']) }} as supplier_product_performance_key,
    performance.period_start,
    performance.supplier_key,
    performance.product_key,
    performance.variation_key,
    performance.source_system,
    performance.source_supplier_id,
    performance.source_product_id,
    performance.source_variation_id,
    product.product_name,
    variation.variation_name,
    product.is_deleted as product_is_deleted,
    variation.is_deleted as variation_is_deleted,
    performance.units_sold,
    performance.revenue_ht,
    performance.revenue_ttc,
    performance.order_count,
    ranked_products.product_units_sold,
    ranked_products.product_revenue_ht,
    ranked_products.product_rank,
    performance.last_updated_at
from variation_performance as performance
inner join ranked_products
    on ranked_products.period_start = performance.period_start
    and ranked_products.source_system = performance.source_system
    and ranked_products.source_supplier_id = performance.source_supplier_id
    and ranked_products.source_product_id = performance.source_product_id
left join {{ ref('dim_product') }} as product
    on product.product_key = performance.product_key
left join {{ ref('dim_variation') }} as variation
    on variation.variation_key = performance.variation_key
