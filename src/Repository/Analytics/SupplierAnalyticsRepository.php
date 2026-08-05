<?php

namespace App\Repository\Analytics;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Dto\Analytics\DailyKpiDto;
use App\Dto\Analytics\KpiSummaryDto;
use App\Dto\Analytics\OrderStatusDto;
use App\Dto\Analytics\ProductPerformanceDto;
use App\Dto\Analytics\StockOverviewDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SupplierAnalyticsRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.data_warehouse_connection')]
        private Connection $connection,
    ) {
    }

    public function summary(
        int $supplierId,
        AnalyticsDateRange $range,
    ): KpiSummaryDto {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                select
                    coalesce(sum(order_count), 0)::bigint as order_count,
                    coalesce(sum(cancelled_order_count), 0)::bigint as cancelled_order_count,
                    case
                        when coalesce(sum(order_count), 0) = 0 then 0
                        else round(
                            sum(cancelled_order_count)::numeric / sum(order_count),
                            6
                        )
                    end as cancellation_rate,
                    coalesce(sum(revenue_ht), 0)::numeric(18, 3) as revenue_ht,
                    coalesce(sum(revenue_ttc), 0)::numeric(18, 3) as revenue_ttc,
                    coalesce(sum(ordered_units), 0)::bigint as ordered_units,
                    case
                        when coalesce(sum(order_count - cancelled_order_count), 0) = 0 then 0
                        else round(
                            sum(revenue_ht) / sum(order_count - cancelled_order_count),
                            3
                        )
                    end as average_order_value_ht,
                    case
                        when coalesce(sum(order_count - cancelled_order_count), 0) = 0 then 0
                        else round(
                            sum(revenue_ttc) / sum(order_count - cancelled_order_count),
                            3
                        )
                    end as average_order_value_ttc,
                    max(last_updated_at) as last_updated_at
                from analytics.mart_supplier_daily_kpi
                where source_supplier_id = :supplier_id
                  and calendar_date between :date_from and :date_to
                SQL,
            $this->rangeParameters($supplierId, $range),
            $this->rangeParameterTypes(),
        );

        return KpiSummaryDto::fromRow($row ?: [
            'order_count' => 0,
            'cancelled_order_count' => 0,
            'cancellation_rate' => 0,
            'revenue_ht' => 0,
            'revenue_ttc' => 0,
            'ordered_units' => 0,
            'average_order_value_ht' => 0,
            'average_order_value_ttc' => 0,
            'last_updated_at' => null,
        ]);
    }

    /** @return list<DailyKpiDto> */
    public function evolution(
        int $supplierId,
        AnalyticsDateRange $range,
    ): array {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                select
                    calendar_date,
                    order_count,
                    cancelled_order_count,
                    revenue_ht,
                    revenue_ttc,
                    ordered_units
                from analytics.mart_supplier_daily_kpi
                where source_supplier_id = :supplier_id
                  and calendar_date between :date_from and :date_to
                order by calendar_date
                SQL,
            $this->rangeParameters($supplierId, $range),
            $this->rangeParameterTypes(),
        );

        return array_map(DailyKpiDto::fromRow(...), $rows);
    }

    /** @return list<ProductPerformanceDto> */
    public function productPerformance(
        int $supplierId,
        AnalyticsDateRange $range,
        int $limit,
    ): array {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                select
                    period_start,
                    source_product_id,
                    source_variation_id,
                    product_name,
                    variation_name,
                    units_sold,
                    revenue_ht,
                    revenue_ttc,
                    order_count,
                    product_rank,
                    product_is_deleted,
                    variation_is_deleted,
                    last_updated_at
                from analytics.mart_supplier_product_performance
                where source_supplier_id = :supplier_id
                  and period_start between
                      date_trunc('month', cast(:date_from as date))::date
                      and date_trunc('month', cast(:date_to as date))::date
                order by period_start desc, product_rank, revenue_ht desc,
                    source_product_id, source_variation_id
                limit :result_limit
                SQL,
            [
                ...$this->rangeParameters($supplierId, $range),
                'result_limit' => $limit,
            ],
            [
                ...$this->rangeParameterTypes(),
                'result_limit' => ParameterType::INTEGER,
            ],
        );

        return array_map(ProductPerformanceDto::fromRow(...), $rows);
    }

    /** @return list<OrderStatusDto> */
    public function orderStatuses(int $supplierId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                select
                    order_status,
                    current_order_count,
                    current_order_share,
                    measured_transition_count,
                    average_transition_duration_seconds,
                    last_updated_at
                from analytics.mart_supplier_order_status
                where source_supplier_id = :supplier_id
                order by case order_status
                    when 'EN_ATTENTE_CONFIRMATION' then 1
                    when 'EN_PREPARATION' then 2
                    when 'PRETE' then 3
                    when 'EXPEDIEE' then 4
                    when 'EN_LIVRAISON' then 5
                    when 'LIVREE' then 6
                    when 'ANNULEE' then 7
                    else 99
                end
                SQL,
            ['supplier_id' => $supplierId],
            ['supplier_id' => ParameterType::INTEGER],
        );

        return array_map(OrderStatusDto::fromRow(...), $rows);
    }

    /** @return list<StockOverviewDto> */
    public function stockOverview(int $supplierId, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                select
                    source_product_id,
                    source_variation_id,
                    product_name,
                    variation_name,
                    stock_registered,
                    stock_used,
                    stock_reserved,
                    stock_physical,
                    stock_available,
                    is_currently_out_of_stock,
                    has_observed_stockout,
                    last_movement_type,
                    last_movement_quantity,
                    last_movement_at,
                    product_is_deleted,
                    variation_is_deleted,
                    last_updated_at
                from analytics.mart_supplier_stock_overview
                where source_supplier_id = :supplier_id
                order by is_currently_out_of_stock desc, stock_available,
                    source_product_id, source_variation_id
                limit :result_limit
                SQL,
            [
                'supplier_id' => $supplierId,
                'result_limit' => $limit,
            ],
            [
                'supplier_id' => ParameterType::INTEGER,
                'result_limit' => ParameterType::INTEGER,
            ],
        );

        return array_map(StockOverviewDto::fromRow(...), $rows);
    }

    private function rangeParameters(
        int $supplierId,
        AnalyticsDateRange $range,
    ): array {
        return [
            'supplier_id' => $supplierId,
            'date_from' => $range->from->format('Y-m-d'),
            'date_to' => $range->to->format('Y-m-d'),
        ];
    }

    private function rangeParameterTypes(): array
    {
        return [
            'supplier_id' => ParameterType::INTEGER,
            'date_from' => ParameterType::STRING,
            'date_to' => ParameterType::STRING,
        ];
    }
}
