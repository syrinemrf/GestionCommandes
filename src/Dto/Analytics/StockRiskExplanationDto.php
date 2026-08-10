<?php

namespace App\Dto\Analytics;

final readonly class StockRiskExplanationDto
{
    public function __construct(
        public int $variationId,
        public string $productName,
        public ?string $variationName,
        public int $stockAvailable,
        public float $forecastCentral7d,
        public float $forecastQ90_7d,
        public int $deficit,
        public string $risk,
        public int $recommendedQuantity,
        public array $factors,
        public string $modelVersion,
        public string $predictedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $business = self::decodeJson($row['business_explanation']);

        return new self(
            (int) $row['source_variation_id'],
            (string) $row['product_name'],
            $row['variation_name'] !== null ? (string) $row['variation_name'] : null,
            (int) ($business['stock_available'] ?? $row['stock_available']),
            (float) ($business['forecast_central_7d'] ?? $row['forecast_central_7d']),
            (float) ($business['forecast_q90_7d'] ?? $row['forecast_q90_7d']),
            (int) ($business['deficit'] ?? 0),
            (string) ($business['risk'] ?? $row['risk']),
            (int) ($business['recommended_quantity'] ?? $row['recommended_quantity']),
            self::decodeJson($row['shap_factors']),
            (string) $row['model_version'],
            (string) $row['predicted_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'variationId' => $this->variationId,
            'productName' => $this->productName,
            'variationName' => $this->variationName,
            'stockAvailable' => $this->stockAvailable,
            'forecastCentral7d' => $this->forecastCentral7d,
            'forecastQ90_7d' => $this->forecastQ90_7d,
            'deficit' => $this->deficit,
            'risk' => $this->risk,
            'recommendedQuantity' => $this->recommendedQuantity,
            'factors' => $this->factors,
            'shapAvailable' => $this->factors !== [],
            'modelVersion' => $this->modelVersion,
            'predictedAt' => $this->predictedAt,
            'wording' => 'Ces facteurs contribuent à la prévision ; ils ne prouvent pas une causalité.',
        ];
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
