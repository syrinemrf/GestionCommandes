select *
from {{ ref('fact_order_line') }}
where quantity <= 0
