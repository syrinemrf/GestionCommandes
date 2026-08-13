select *
from {{ ref('mart_supplier_stock_risk') }}
where forecast_central_7d < 0
   or forecast_q90_7d < 0
   or stock_available < 0
   or recommended_quantity < 0
