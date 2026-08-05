select *
from {{ ref('fact_stock_movement') }}
where stock_physical_before < 0
   or stock_physical_after < 0
   or stock_reserved_before < 0
   or stock_reserved_after < 0
