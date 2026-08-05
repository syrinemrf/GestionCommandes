with violations as (
    select 'daily_kpi' as mart_name, mart.source_supplier_id
    from {{ ref('mart_supplier_daily_kpi') }} as mart
    inner join {{ ref('dim_supplier') }} as supplier on supplier.supplier_key = mart.supplier_key
    where supplier.source_system <> mart.source_system
       or supplier.source_supplier_id <> mart.source_supplier_id

    union all

    select 'product_performance', mart.source_supplier_id
    from {{ ref('mart_supplier_product_performance') }} as mart
    inner join {{ ref('dim_product') }} as product on product.product_key = mart.product_key
    inner join {{ ref('dim_variation') }} as variation on variation.variation_key = mart.variation_key
    where product.source_supplier_id <> mart.source_supplier_id
       or variation.source_supplier_id <> mart.source_supplier_id
       or product.source_system <> mart.source_system
       or variation.source_system <> mart.source_system

    union all

    select 'order_status', mart.source_supplier_id
    from {{ ref('mart_supplier_order_status') }} as mart
    inner join {{ ref('dim_supplier') }} as supplier on supplier.supplier_key = mart.supplier_key
    where supplier.source_system <> mart.source_system
       or supplier.source_supplier_id <> mart.source_supplier_id

    union all

    select 'stock_overview', mart.source_supplier_id
    from {{ ref('mart_supplier_stock_overview') }} as mart
    inner join {{ ref('dim_product') }} as product on product.product_key = mart.product_key
    inner join {{ ref('dim_variation') }} as variation on variation.variation_key = mart.variation_key
    where product.source_supplier_id <> mart.source_supplier_id
       or variation.source_supplier_id <> mart.source_supplier_id
       or product.source_system <> mart.source_system
       or variation.source_system <> mart.source_system
)
select * from violations
