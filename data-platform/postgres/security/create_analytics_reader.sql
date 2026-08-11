\set ON_ERROR_STOP on

\if :{?analytics_reader_password}
\else
    \echo 'Missing psql variable: analytics_reader_password'
    \quit 1
\endif

select 'create role comdely_analytics_reader login'
where not exists (
    select 1 from pg_roles where rolname = 'comdely_analytics_reader'
) \gexec

select format(
    'alter role comdely_analytics_reader password %L',
    :'analytics_reader_password'
) \gexec

alter role comdely_analytics_reader nosuperuser nocreatedb nocreaterole
    noinherit noreplication;
alter role comdely_analytics_reader set default_transaction_read_only = on;

grant connect on database comdely_dw to comdely_analytics_reader;
grant usage on schema analytics to comdely_analytics_reader;

revoke all on schema raw, staging, marts, meta
    from comdely_analytics_reader;
revoke all on all tables in schema raw, staging, marts, meta
    from comdely_analytics_reader;
revoke all on all tables in schema analytics
    from comdely_analytics_reader;

grant select on table
    analytics.mart_supplier_daily_kpi,
    analytics.mart_supplier_product_performance,
    analytics.mart_supplier_order_status,
    analytics.mart_supplier_order_processing_time,
    analytics.mart_supplier_stock_overview,
    analytics.mart_supplier_stock_risk
to comdely_analytics_reader;

revoke create on schema public from comdely_analytics_reader;
