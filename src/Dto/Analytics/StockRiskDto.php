<?php

namespace App\Dto\Analytics;

final readonly class StockRiskDto
{
    public function __construct(
        public int $productId,
        public int $variationId,
        public string $productName,
        public ?string $variationName,
        public string $predictionDate,
        public string $predictedAt,
        public float $forecastCentral7d,
        public float $forecastQ90_7d,
        public int $stockAvailable,
        public string $risk,
        public int $recommendedQuantity,
        public string $modelVersion,
        public array $demandHistory,
        public bool $shapAvailable,
        public string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $history = self::decodeJson($row['demand_history']);
        $factors = self::decodeJson($row['shap_factors']);

        return new self(
            (int) $row['source_product_id'],
            (int) $row['source_variation_id'],
            (string) $row['product_name'],
            $row['variation_name'] !== null ? (string) $row['variation_name'] : null,
            (string) $row['prediction_date'],
            (string) $row['predicted_at'],
            (float) $row['forecast_central_7d'],
            (float) $row['forecast_q90_7d'],
            (int) $row['stock_available'],
            (string) $row['risk'],
            (int) $row['recommended_quantity'],
            (string) $row['model_version'],
            $history,
            $factors !== [],
            (string) $row['last_updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'variationId' => $this->variationId,
            'productName' => $this->productName,
            'variationName' => $this->variationName,
            'predictionDate' => $this->predictionDate,
            'predictedAt' => $this->predictedAt,
            'forecastCentral7d' => $this->forecastCentral7d,
            'forecastQ90_7d' => $this->forecastQ90_7d,
            'stockAvailable' => $this->stockAvailable,
            'risk' => $this->risk,
            'recommendedQuantity' => $this->recommendedQuantity,
            'modelVersion' => $this->modelVersion,
            'demandHistory' => $this->demandHistory,
            'shapAvailable' => $this->shapAvailable,
            'reason' => $this->reason(),
            'recommendedAction' => $this->recommendedAction(),
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }

    private function reason(): string
    {
        return match ($this->risk) {
            'HIGH' => $this->stockAvailable <= 0
                ? 'Le stock est nul et la demande prévue dépasse le stock disponible.'
                : 'La demande prévue à 7 jours dépasse le stock disponible.',
            'MEDIUM' => 'Le stock couvre la demande la plus probable, mais la marge de sécurité est limitée.',
            'LOW' => 'Le stock disponible couvre la demande prévue et sa marge d’incertitude.',
            default => 'L’historique est encore insuffisant pour établir un risque fiable.',
        };
    }

    private function recommendedAction(): string
    {
        return match ($this->risk) {
            'HIGH' => $this->recommendedQuantity > 0
                ? sprintf('Réapprovisionner rapidement d’environ %d unités.', $this->recommendedQuantity)
                : 'Réapprovisionner rapidement.',
            'MEDIUM' => $this->recommendedQuantity > 0
                ? sprintf('Planifier un réapprovisionnement d’environ %d unités.', $this->recommendedQuantity)
                : 'Surveiller les prochaines ventes.',
            'LOW' => 'Aucune action immédiate recommandée.',
            default => 'Continuer à collecter des ventes et surveiller manuellement le stock.',
        };
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
