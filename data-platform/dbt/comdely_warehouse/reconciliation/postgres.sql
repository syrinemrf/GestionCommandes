-- Executer sur comdely_dw apres `dbt build`.

select
    calendar_date,
    source_supplier_id,
    order_count,
    cancelled_order_count,
    cancellation_rate,
    revenue_ht,
    revenue_ttc,
    ordered_units
from analytics.mart_supplier_daily_kpi
where order_count > 0
order by calendar_date, source_supplier_id;

select
    source_supplier_id,
    order_status,
    current_order_count
from analytics.mart_supplier_order_status
order by source_supplier_id, order_status;

select
    source_supplier_id,
    source_product_id,
    source_variation_id,
    stock_registered,
    stock_used,
    stock_reserved,
    stock_physical,
    stock_available
from analytics.mart_supplier_stock_overview
order by source_supplier_id, source_product_id, source_variation_id;
