with event_dates as (
    select ordered_at::date as calendar_date from {{ ref('stg_order') }}
    union all
    select occurred_at::date from {{ ref('stg_stock_movement') }}
    union all
    select changed_at::date from {{ ref('stg_order_status_history') }}
),
bounds as (
    select
        date_trunc('year', coalesce(min(calendar_date), current_date))::date as start_date,
        (
            date_trunc('year', coalesce(max(calendar_date), current_date))
            + interval '1 year' - interval '1 day'
        )::date as end_date
    from event_dates
),
date_spine as (
    select generated_at::date as calendar_date
    from bounds
    cross join lateral generate_series(
        bounds.start_date,
        bounds.end_date,
        interval '1 day'
    ) as generated(generated_at)
)
select
    to_char(calendar_date, 'YYYYMMDD')::integer as date_key,
    calendar_date,
    extract(isoyear from calendar_date)::integer as iso_year,
    extract(year from calendar_date)::integer as year,
    extract(quarter from calendar_date)::integer as quarter,
    extract(month from calendar_date)::integer as month,
    to_char(calendar_date, 'TMMonth')::text as month_name,
    extract(week from calendar_date)::integer as iso_week,
    extract(day from calendar_date)::integer as day_of_month,
    extract(isodow from calendar_date)::integer as iso_day_of_week,
    to_char(calendar_date, 'TMDay')::text as day_name,
    (extract(isodow from calendar_date) in (6, 7))::boolean as is_weekend
from date_spine
