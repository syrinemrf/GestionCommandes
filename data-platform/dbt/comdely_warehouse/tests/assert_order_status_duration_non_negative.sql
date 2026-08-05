select *
from {{ ref('fact_order_status_history') }}
where seconds_since_previous_status < 0
