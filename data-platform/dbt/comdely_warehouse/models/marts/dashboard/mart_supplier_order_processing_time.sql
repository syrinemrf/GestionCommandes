{{ config(materialized='table') }}

with order_cycles as (
    select
        history.supplier_key,
        history.source_system,
        history.source_supplier_id,
        history.source_order_id,
        max(history.order_number) as order_number,
        min(history.changed_at) filter (
            where history.new_status = 'EN_PREPARATION'
        ) as preparation_started_at,
        min(history.changed_at) filter (
            where history.new_status = 'PRETE'
        ) as ready_at,
        min(history.changed_at) filter (
            where history.new_status = 'EXPEDIEE'
        ) as shipped_at,
        max(history.extracted_at) as last_updated_at
    from {{ ref('fact_order_status_history') }} as history
    inner join {{ ref('stg_order') }} as orders
        on orders.source_system = history.source_system
        and orders.source_order_id = history.source_order_id
        and orders.source_supplier_id = history.source_supplier_id
    where not orders.is_deleted
    group by
        history.supplier_key,
        history.source_system,
        history.source_supplier_id,
        history.source_order_id
),
valid_cycles as (
    select *
    from order_cycles
    where preparation_started_at is not null
      and shipped_at is not null
      and shipped_at >= preparation_started_at
      and (
          ready_at is null
          or ready_at between preparation_started_at and shipped_at
      )
)
select
    {{ stable_surrogate_key(['source_system', 'source_order_id']) }} as supplier_order_processing_key,
    supplier_key,
    source_system,
    source_supplier_id,
    source_order_id,
    order_number,
    preparation_started_at,
    ready_at,
    shipped_at,
    shipped_at::date as processing_completed_date,
    extract(epoch from (shipped_at - preparation_started_at))::bigint
        as processing_seconds,
    case
        when ready_at is not null
            then extract(epoch from (ready_at - preparation_started_at))::bigint
    end as preparation_to_ready_seconds,
    case
        when ready_at is not null
            then extract(epoch from (shipped_at - ready_at))::bigint
    end as ready_to_shipped_seconds,
    last_updated_at
from valid_cycles
