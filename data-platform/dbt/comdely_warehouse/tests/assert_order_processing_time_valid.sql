select *
from {{ ref('mart_supplier_order_processing_time') }}
where processing_seconds < 0
   or preparation_to_ready_seconds < 0
   or ready_to_shipped_seconds < 0
   or shipped_at < preparation_started_at
   or (ready_at is not null and ready_at not between preparation_started_at and shipped_at)
