select *
from {{ ref('mart_ml_daily_demand') }}
where demand_date > current_date
