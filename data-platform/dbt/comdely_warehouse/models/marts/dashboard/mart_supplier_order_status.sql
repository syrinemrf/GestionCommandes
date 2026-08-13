{{ config(materialized='table') }}

with statuses(order_status) as (
    values
        ('EN_ATTENTE_CONFIRMATION'),
        ('EN_PREPARATION'),
        ('PRETE'),
        ('EXPEDIEE'),
        ('EN_LIVRAISON'),
        ('LIVREE'),
        ('ANNULEE')
),
supplier_statuses as (
    select
        supplier.supplier_key,
        supplier.source_system,
        supplier.source_supplier_id,
        supplier.extracted_at as supplier_extracted_at,
        statuses.order_status
    from {{ ref('dim_supplier') }} as supplier
    cross join statuses
),
current_status as (
    select
        source_system,
        source_supplier_id,
        order_status,
        count(*)::bigint as current_order_count,
        max(extracted_at) as last_updated_at
    from {{ ref('stg_order') }}
    where not is_deleted
    group by source_system, source_supplier_id, order_status
),
supplier_totals as (
    select
        source_system,
        source_supplier_id,
        count(*)::bigint as current_order_total
    from {{ ref('stg_order') }}
    where not is_deleted
    group by source_system, source_supplier_id
),
transition_durations as (
    select
        history.source_system,
        history.source_supplier_id,
        history.new_status as order_status,
        count(*)::bigint as measured_transition_count,
        avg(history.seconds_since_previous_status)::numeric(18, 3) as average_transition_duration_seconds,
        max(history.extracted_at) as last_updated_at
    from {{ ref('fact_order_status_history') }} as history
    inner join {{ ref('stg_order') }} as orders
        on orders.source_system = history.source_system
        and orders.source_order_id = history.source_order_id
    where not orders.is_deleted
      and history.seconds_since_previous_status is not null
    group by history.source_system, history.source_supplier_id, history.new_status
)
select
    {{ stable_surrogate_key(['supplier_statuses.source_system', 'supplier_statuses.source_supplier_id', 'supplier_statuses.order_status']) }} as supplier_order_status_key,
    supplier_statuses.supplier_key,
    supplier_statuses.source_system,
    supplier_statuses.source_supplier_id,
    supplier_statuses.order_status,
    coalesce(current_status.current_order_count, 0)::bigint as current_order_count,
    case
        when coalesce(supplier_totals.current_order_total, 0) = 0 then 0::numeric(12, 6)
        else round(
            coalesce(current_status.current_order_count, 0)::numeric / supplier_totals.current_order_total,
            6
        )::numeric(12, 6)
    end as current_order_share,
    coalesce(transition_durations.measured_transition_count, 0)::bigint as measured_transition_count,
    transition_durations.average_transition_duration_seconds,
    greatest(
        supplier_statuses.supplier_extracted_at,
        current_status.last_updated_at,
        transition_durations.last_updated_at
    ) as last_updated_at
from supplier_statuses
left join current_status
    on current_status.source_system = supplier_statuses.source_system
    and current_status.source_supplier_id = supplier_statuses.source_supplier_id
    and current_status.order_status = supplier_statuses.order_status
left join supplier_totals
    on supplier_totals.source_system = supplier_statuses.source_system
    and supplier_totals.source_supplier_id = supplier_statuses.source_supplier_id
left join transition_durations
    on transition_durations.source_system = supplier_statuses.source_system
    and transition_durations.source_supplier_id = supplier_statuses.source_supplier_id
    and transition_durations.order_status = supplier_statuses.order_status
