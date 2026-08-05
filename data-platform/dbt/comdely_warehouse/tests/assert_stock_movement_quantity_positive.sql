select *
from {{ ref('fact_stock_movement') }}
where quantity <= 0
