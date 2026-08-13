{{
    config(
        materialized='incremental',
        unique_key='order_status_history_key',
        incremental_strategy='merge',
        on_schema_change='fail'
    )
}}

with sequenced as (
    select
        history.*,
        row_number() over (
            partition by history.source_system, history.source_order_id
            order by history.changed_at, history.source_status_history_id
        )::integer as transition_sequence,
        lag(history.changed_at) over (
            partition by history.source_system, history.source_order_id
            order by history.changed_at, history.source_status_history_id
        ) as previous_changed_at
    from {{ ref('stg_order_status_history') }} as history
),
incremental_rows as (
    select *
    from sequenced
    {% if is_incremental() %}
    where source_status_history_id > (
        select coalesce(max(source_status_history_id), 0) from {{ this }}
    )
    {% endif %}
)
select
    {{ stable_surrogate_key(['history.source_system', 'history.source_status_history_id']) }} as order_status_history_key,
    case
        when orders.source_supplier_id is not null
            then {{ stable_surrogate_key(['orders.source_system', 'orders.source_supplier_id']) }}
    end as supplier_key,
    to_char(history.changed_at::date, 'YYYYMMDD')::integer as status_date_key,
    history.source_system,
    history.source_status_history_id,
    history.source_order_id,
    orders.order_number,
    orders.source_supplier_id,
    history.source_changed_by_user_id,
    history.previous_status,
    history.new_status,
    history.changed_at,
    history.transition_sequence,
    history.previous_changed_at,
    case
        when history.previous_changed_at is not null
            then extract(epoch from (history.changed_at - history.previous_changed_at))::bigint
    end as seconds_since_previous_status,
    history.extracted_at
from incremental_rows as history
left join {{ ref('stg_order') }} as orders
    on orders.source_system = history.source_system
    and orders.source_order_id = history.source_order_id
