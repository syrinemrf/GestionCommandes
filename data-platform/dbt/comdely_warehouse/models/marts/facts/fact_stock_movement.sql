{{
    config(
        materialized='incremental',
        unique_key='stock_movement_key',
        incremental_strategy='merge',
        on_schema_change='fail'
    )
}}

with movements as (
    select *
    from {{ ref('stg_stock_movement') }}
    {% if is_incremental() %}
    where source_stock_movement_id > (
        select coalesce(max(source_stock_movement_id), 0) from {{ this }}
    )
    {% endif %}
)
select
    {{ stable_surrogate_key(['movement.source_system', 'movement.source_stock_movement_id']) }} as stock_movement_key,
    case
        when product.source_supplier_id is not null
            then {{ stable_surrogate_key(['product.source_system', 'product.source_supplier_id']) }}
    end as supplier_key,
    {{ stable_surrogate_key(['movement.source_system', 'variation.source_product_id']) }} as product_key,
    {{ stable_surrogate_key(['movement.source_system', 'movement.source_variation_id']) }} as variation_key,
    to_char(movement.occurred_at::date, 'YYYYMMDD')::integer as movement_date_key,
    movement.source_system,
    movement.source_stock_movement_id,
    movement.source_variation_id,
    variation.source_product_id,
    product.source_supplier_id,
    movement.source_order_id,
    movement.source_created_by_user_id,
    movement.movement_type,
    movement.quantity,
    movement.stock_physical_before,
    movement.stock_physical_after,
    (movement.stock_physical_after - movement.stock_physical_before)::bigint as stock_physical_delta,
    movement.stock_reserved_before,
    movement.stock_reserved_after,
    (movement.stock_reserved_after - movement.stock_reserved_before)::bigint as stock_reserved_delta,
    movement.occurred_at,
    movement.comment,
    movement.demo_batch,
    movement.extracted_at
from movements as movement
left join {{ ref('stg_product_variation') }} as variation
    on variation.source_system = movement.source_system
    and variation.source_variation_id = movement.source_variation_id
left join {{ ref('stg_product') }} as product
    on product.source_system = variation.source_system
    and product.source_product_id = variation.source_product_id
