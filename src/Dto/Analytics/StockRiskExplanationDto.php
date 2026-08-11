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
        public array $demandHistory = [],
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
            self::decodeJson($row['demand_history'] ?? null),
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
            'recommendedAction' => $this->recommendedAction(),
            'factors' => $this->factors,
            'insights' => $this->businessInsights(),
            'recentTrend' => $this->recentTrend(),
            'variability' => $this->variability(),
            'shapAvailable' => $this->factors !== [],
            'modelVersion' => $this->modelVersion,
            'predictedAt' => $this->predictedAt,
            'wording' => 'Ces facteurs contribuent à la prévision ; ils ne prouvent pas une causalité.',
        ];
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

    private function businessInsights(): array
    {
        return array_map(function (array $factor): string {
            $name = (string) ($factor['name'] ?? '');
            $direction = ($factor['direction'] ?? '') === 'INCREASES'
                ? 'à augmenter'
                : (($factor['direction'] ?? '') === 'DECREASES' ? 'à réduire' : 'peu à modifier');
            $subject = match (true) {
                str_contains($name, 'rolling_') => 'Le niveau et le rythme des ventes récentes',
                str_contains($name, 'trend') => 'La tendance récente de la demande',
                str_contains($name, 'zero_'), str_contains($name, 'intermittent') => 'La fréquence des jours sans vente',
                str_contains($name, 'lag_') => 'L’historique récent des ventes',
                str_contains($name, 'day_'), str_contains($name, 'week_'), str_contains($name, 'month'), str_contains($name, 'quarter') => 'La période du calendrier',
                default => 'Le profil de vente habituel de cette variation',
            };

            return sprintf('%s contribue %s la prévision.', $subject, $direction);
        }, array_slice($this->factors, 0, 3));
    }

    private function recentTrend(): string
    {
        $quantities = $this->quantities();
        if (count($quantities) < 8) {
            return 'Historique récent insuffisant';
        }
        $recent = array_sum(array_slice($quantities, -7));
        $previous = array_sum(array_slice($quantities, -14, 7));
        if ($previous <= 0) {
            return $recent > 0 ? 'Demande en reprise' : 'Demande stable et faible';
        }
        $change = (($recent - $previous) / $previous) * 100;
        if (abs($change) < 10) {
            return 'Demande globalement stable';
        }

        return $change > 0
            ? sprintf('Demande en hausse d’environ %.0f %%', abs($change))
            : sprintf('Demande en baisse d’environ %.0f %%', abs($change));
    }

    private function variability(): string
    {
        $values = $this->quantities();
        if (count($values) < 2) {
            return 'Non disponible';
        }
        $mean = array_sum($values) / count($values);
        if ($mean <= 0) {
            return 'Faible';
        }
        $variance = array_sum(array_map(
            static fn (float $value): float => ($value - $mean) ** 2,
            $values,
        )) / count($values);
        $coefficient = sqrt($variance) / $mean;

        return $coefficient < 0.5 ? 'Faible' : ($coefficient < 1.0 ? 'Modérée' : 'Élevée');
    }

    private function quantities(): array
    {
        return array_map(
            static fn (array $point): float => max(0, (float) ($point['quantity'] ?? 0)),
            $this->demandHistory,
        );
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
