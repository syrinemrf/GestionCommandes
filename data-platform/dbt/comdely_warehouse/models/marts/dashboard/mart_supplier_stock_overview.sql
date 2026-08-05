{{ config(materialized='table') }}

with movement_history as (
    select
        source_system,
        source_variation_id,
        bool_or((stock_physical_after - stock_reserved_after) <= 0) as has_observed_stockout,
        max(extracted_at) as movement_last_updated_at
    from {{ ref('fact_stock_movement') }}
    group by source_system, source_variation_id
),
latest_movement as (
    select *
    from (
        select
            source_system,
            source_variation_id,
            movement_type,
            quantity,
            occurred_at,
            row_number() over (
                partition by source_system, source_variation_id
                order by occurred_at desc, source_stock_movement_id desc
            ) as movement_rank
        from {{ ref('fact_stock_movement') }}
    ) as ranked_movements
    where movement_rank = 1
)
select
    {{ stable_surrogate_key(['variation.source_system', 'variation.source_supplier_id', 'variation.source_product_id', 'variation.source_variation_id']) }} as supplier_stock_overview_key,
    variation.supplier_key,
    variation.product_key,
    variation.variation_key,
    variation.source_system,
    variation.source_supplier_id,
    variation.source_product_id,
    variation.source_variation_id,
    product.product_name,
    variation.variation_name,
    product.is_deleted as product_is_deleted,
    variation.is_deleted as variation_is_deleted,
    variation.stock_initial as stock_registered,
    variation.stock_used,
    variation.stock_reserved,
    (variation.stock_initial - variation.stock_used)::bigint as stock_physical,
    (variation.stock_initial - variation.stock_used - variation.stock_reserved)::bigint as stock_available,
    ((variation.stock_initial - variation.stock_used - variation.stock_reserved) <= 0) as is_currently_out_of_stock,
    coalesce(movement_history.has_observed_stockout, false) as has_observed_stockout,
    latest_movement.movement_type as last_movement_type,
    latest_movement.quantity as last_movement_quantity,
    latest_movement.occurred_at as last_movement_at,
    greatest(variation.extracted_at, movement_history.movement_last_updated_at) as last_updated_at
from {{ ref('dim_variation') }} as variation
inner join {{ ref('dim_product') }} as product
    on product.product_key = variation.product_key
left join movement_history
    on movement_history.source_system = variation.source_system
    and movement_history.source_variation_id = variation.source_variation_id
left join latest_movement
    on latest_movement.source_system = variation.source_system
    and latest_movement.source_variation_id = variation.source_variation_id
