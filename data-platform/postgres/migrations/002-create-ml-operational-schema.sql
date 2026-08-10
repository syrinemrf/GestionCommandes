begin;

create schema if not exists ml;

create table if not exists ml.model_registry (
    model_id bigint generated always as identity primary key,
    version text not null unique,
    algorithm text not null,
    trained_at timestamptz not null,
    training_end_date date not null,
    features jsonb not null,
    parameters jsonb not null,
    metrics jsonb not null,
    artifact_path text not null,
    status text not null,
    created_at timestamptz not null default now(),
    constraint model_registry_status_check check (status in ('CANDIDATE', 'CHAMPION', 'REJECTED'))
);

create unique index if not exists model_registry_single_champion_idx
    on ml.model_registry ((status)) where status = 'CHAMPION';

create table if not exists ml.stock_prediction (
    prediction_id bigint generated always as identity primary key,
    prediction_date date not null,
    predicted_at timestamptz not null,
    source_system text not null,
    source_supplier_id bigint not null,
    source_product_id bigint not null,
    source_variation_id bigint not null,
    forecast_central_7d numeric(18, 4) not null,
    forecast_q90_7d numeric(18, 4) not null,
    stock_available bigint not null,
    risk text not null,
    recommended_quantity bigint not null,
    model_version text not null references ml.model_registry(version),
    created_at timestamptz not null default now(),
    constraint stock_prediction_central_non_negative check (forecast_central_7d >= 0),
    constraint stock_prediction_q90_non_negative check (forecast_q90_7d >= 0),
    constraint stock_prediction_stock_non_negative check (stock_available >= 0),
    constraint stock_prediction_recommendation_non_negative check (recommended_quantity >= 0),
    constraint stock_prediction_risk_check check (risk in ('HIGH', 'MEDIUM', 'LOW', 'INSUFFICIENT_DATA')),
    constraint stock_prediction_grain_unique unique (prediction_date, source_system, source_variation_id)
);

create index if not exists stock_prediction_supplier_date_idx
    on ml.stock_prediction (source_supplier_id, prediction_date desc);

commit;
