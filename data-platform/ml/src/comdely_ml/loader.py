from __future__ import annotations

import pandas as pd
from sqlalchemy import create_engine, text

from .config import Settings


QUERY = text(
    '''
    select
        demand_date,
        supplier_key,
        product_key,
        variation_key,
        source_supplier_id,
        source_product_id,
        source_variation_id,
        supplier_label,
        product_name,
        variation_name,
        product_created_at,
        product_is_deleted,
        variation_is_deleted,
        demand_quantity
    from analytics.mart_ml_daily_demand
    where demand_date between :dataset_start and :dataset_end
      and demand_date <= current_date
    order by variation_key, demand_date
    '''
)


def load_daily_demand(settings: Settings) -> pd.DataFrame:
    '''Load the modeling mart through a read-only PostgreSQL transaction.'''
    engine = create_engine(settings.database_url, pool_pre_ping=True)
    try:
        with engine.connect() as connection:
            connection.execute(text('set transaction read only'))
            frame = pd.read_sql_query(
                QUERY,
                connection,
                params={
                    'dataset_start': settings.dataset_start,
                    'dataset_end': settings.dataset_end,
                },
                parse_dates=['demand_date', 'product_created_at'],
            )
    finally:
        engine.dispose()

    return frame
