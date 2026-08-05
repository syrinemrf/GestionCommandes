{{ config(materialized='table') }}

select
    {{ stable_surrogate_key(['line.source_system', 'line.source_order_line_id']) }} as order_line_key,
    case
        when orders.source_supplier_id is not null
            then {{ stable_surrogate_key(['orders.source_system', 'orders.source_supplier_id']) }}
    end as supplier_key,
    case
        when orders.source_client_id is not null
            then {{ stable_surrogate_key(['orders.source_system', 'orders.source_client_id']) }}
    end as client_key,
    {{ stable_surrogate_key(['line.source_system', 'line.source_product_id']) }} as product_key,
    {{ stable_surrogate_key(['line.source_system', 'line.source_variation_id']) }} as variation_key,
    to_char(orders.ordered_at::date, 'YYYYMMDD')::integer as order_date_key,
    line.source_system,
    line.source_order_line_id,
    line.source_order_id,
    orders.order_number,
    orders.source_supplier_id,
    orders.source_client_id,
    line.source_product_id,
    line.source_variation_id,
    line.historical_product_name,
    line.historical_variation_name,
    orders.ordered_at,
    orders.order_status as current_order_status,
    orders.is_deleted as order_is_deleted,
    line.quantity,
    line.historical_unit_price_ht,
    line.line_total_ht,
    orders.tax_rate,
    round(line.line_total_ht * orders.tax_rate / 100, 3)::numeric(18, 3) as line_tax_amount,
    round(line.line_total_ht * (1 + orders.tax_rate / 100), 3)::numeric(18, 3) as line_total_ttc,
    greatest(line.extracted_at, orders.extracted_at) as extracted_at
from {{ ref('stg_order_line') }} as line
left join {{ ref('stg_order') }} as orders
    on orders.source_system = line.source_system
    and orders.source_order_id = line.source_order_id
