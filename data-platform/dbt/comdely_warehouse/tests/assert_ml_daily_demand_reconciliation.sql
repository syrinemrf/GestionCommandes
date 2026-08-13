with mart_total as (
    select sum(demand_quantity)::bigint as quantity
    from {{ ref('mart_ml_daily_demand') }}
),
fact_total as (
    select coalesce(sum(quantity), 0)::bigint as quantity
    from {{ ref('fact_order_line') }}
    where not order_is_deleted
      and current_order_status <> 'ANNULEE'
      and ordered_at::date <= current_date
)
select mart_total.quantity as mart_quantity, fact_total.quantity as fact_quantity
from mart_total
cross join fact_total
where mart_total.quantity <> fact_total.quantity
