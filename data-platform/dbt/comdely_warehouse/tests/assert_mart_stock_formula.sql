select *
from {{ ref('mart_supplier_stock_overview') }}
where stock_physical <> stock_registered - stock_used
   or stock_available <> stock_registered - stock_used - stock_reserved
   or stock_physical < 0
   or stock_available < 0
   or stock_reserved < 0
