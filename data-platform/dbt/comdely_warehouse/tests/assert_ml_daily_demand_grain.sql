select demand_date, variation_key, count(*) as row_count
from {{ ref('mart_ml_daily_demand') }}
group by demand_date, variation_key
having count(*) <> 1
