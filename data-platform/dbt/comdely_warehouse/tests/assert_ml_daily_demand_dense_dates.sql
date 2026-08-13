with per_variation as (
    select
        variation_key,
        count(*)::bigint as actual_rows,
        (max(demand_date) - min(demand_date) + 1)::bigint as expected_rows
    from {{ ref('mart_ml_daily_demand') }}
    group by variation_key
)
select *
from per_variation
where actual_rows <> expected_rows
