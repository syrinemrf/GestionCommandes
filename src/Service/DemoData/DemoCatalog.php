<?php

namespace App\Service\DemoData;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

final class DemoCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/demo/catalog.yaml')]
        private readonly string $catalogPath,
    ) {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $catalog = Yaml::parseFile($this->catalogPath);

        if (!is_array($catalog) || !isset($catalog['suppliers'], $catalog['variation_templates'])) {
            throw new \RuntimeException('Le catalogue de démonstration est invalide.');
        }

        return $this->catalog = $catalog;
    }

    /** @return list<array<string, mixed>> */
    public function suppliers(string $scale): array
    {
        $suppliers = array_values($this->all()['suppliers']);

        return $scale === 'small' ? array_slice($suppliers, 0, 2) : $suppliers;
    }

    /** @return list<array<string, mixed>> */
    public function products(array $supplier, string $scale): array
    {
        $products = array_values($supplier['products'] ?? []);

        return $scale === 'small' ? array_slice($products, 0, 3) : $products;
    }

    /** @return list<array<string, mixed>> */
    public function variations(string $supplierKey, string $scale): array
    {
        $variations = array_values($this->all()['variation_templates'][$supplierKey] ?? []);

        return $scale === 'small' ? array_slice($variations, 0, 2) : $variations;
    }
}
