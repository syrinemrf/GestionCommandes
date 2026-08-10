with date_bounds as (
    select
        min(ordered_at::date) as start_date,
        least(max(ordered_at::date), current_date) as end_date
    from {{ ref('fact_order_line') }}
),
variation_dates as (
    select
        date_day.calendar_date,
        variation.variation_key,
        variation.product_key,
        variation.supplier_key,
        variation.source_system,
        variation.source_supplier_id,
        variation.source_product_id,
        variation.source_variation_id,
        supplier.supplier_label,
        product.product_name,
        product.product_created_at,
        variation.variation_name,
        variation.is_deleted as variation_is_deleted,
        product.is_deleted as product_is_deleted,
        greatest(
            supplier.extracted_at,
            product.extracted_at,
            variation.extracted_at
        ) as dimension_updated_at
    from {{ ref('dim_variation') }} as variation
    inner join {{ ref('dim_product') }} as product
        on product.product_key = variation.product_key
    inner join {{ ref('dim_supplier') }} as supplier
        on supplier.supplier_key = variation.supplier_key
    cross join {{ ref('dim_date') }} as date_day
    cross join date_bounds
    where date_day.calendar_date between date_bounds.start_date and date_bounds.end_date
      and date_day.calendar_date <= current_date
      and date_day.calendar_date >= product.product_created_at::date
),
daily_sales as (
    select
        ordered_at::date as demand_date,
        variation_key,
        sum(quantity)::bigint as demand_quantity,
        max(extracted_at) as sales_updated_at
    from {{ ref('fact_order_line') }}
    where not order_is_deleted
      and current_order_status <> 'ANNULEE'
      and ordered_at::date <= current_date
    group by ordered_at::date, variation_key
)
select
    {{ stable_surrogate_key(['variation_dates.calendar_date', 'variation_dates.variation_key']) }} as ml_daily_demand_key,
    variation_dates.calendar_date as demand_date,
    to_char(variation_dates.calendar_date, 'YYYYMMDD')::integer as date_key,
    variation_dates.supplier_key,
    variation_dates.product_key,
    variation_dates.variation_key,
    variation_dates.source_system,
    variation_dates.source_supplier_id,
    variation_dates.source_product_id,
    variation_dates.source_variation_id,
    variation_dates.supplier_label,
    variation_dates.product_name,
    variation_dates.product_created_at,
    variation_dates.variation_name,
    variation_dates.product_is_deleted,
    variation_dates.variation_is_deleted,
    coalesce(daily_sales.demand_quantity, 0)::bigint as demand_quantity,
    greatest(
        variation_dates.dimension_updated_at,
        daily_sales.sales_updated_at
    ) as last_updated_at
from variation_dates
left join daily_sales
    on daily_sales.demand_date = variation_dates.calendar_date
    and daily_sales.variation_key = variation_dates.variation_key
