<?php

namespace App\Service\DemoData;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DemoImageDownloader
{
    public function __construct(
        private DemoCatalog $catalog,
        private DemoImageRemoteClientInterface $remoteClient,
        #[Autowire('%kernel.project_dir%/public/uploads/demo/products')]
        private string $targetDirectory,
    ) {
    }

    /** @return array{downloaded: int, existing: int, placeholders: int, manifest: string} */
    public function download(): array
    {
        if (!is_dir($this->targetDirectory) && !mkdir($concurrentDirectory = $this->targetDirectory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Impossible de créer le dossier des images de démonstration.');
        }

        $manifestPath = $this->targetDirectory.'/manifest.json';
        $manifest = $this->readManifest($manifestPath);
        $downloaded = 0;
        $existing = 0;
        $placeholders = 0;
        $remoteConfigured = $this->remoteClient->isConfigured();

        foreach ($this->catalog->suppliers('medium') as $supplier) {
            foreach ($this->catalog->products($supplier, 'medium') as $product) {
                $key = (string) $product['key'];
                $query = (string) $product['image_query'];
                $photoPath = $this->targetDirectory.'/'.$key.'.jpg';
                $placeholderPath = $this->targetDirectory.'/'.$key.'.svg';

                if (is_file($photoPath)) {
                    ++$existing;
                    continue;
                }

                try {
                    $photo = $remoteConfigured
                        ? $this->remoteClient->search($query)
                        : null;
                    if ($photo !== null) {
                        $content = $this->remoteClient->download($photo['url']);
                        if (file_put_contents($photoPath, $content, LOCK_EX) === false) {
                            throw new \RuntimeException('Écriture de l’image impossible.');
                        }
                        $manifest[$key] = [
                            'product_key' => $key,
                            'pexels_id' => $photo['id'],
                            'query' => $query,
                            'photo_url' => $photo['photo_url'],
                            'photographer' => $photo['photographer'],
                            'local_file' => 'public/uploads/demo/products/'.$key.'.jpg',
                            'downloaded_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                            'placeholder' => false,
                        ];
                        ++$downloaded;
                        continue;
                    }
                } catch (\Throwable) {
                    // Une indisponibilité distante ne doit jamais bloquer les données métier.
                }

                if (!is_file($placeholderPath)) {
                    file_put_contents($placeholderPath, $this->placeholderSvg((string) $product['name']), LOCK_EX);
                }
                $manifest[$key] = [
                    'product_key' => $key,
                    'pexels_id' => null,
                    'query' => $query,
                    'photo_url' => null,
                    'photographer' => null,
                    'local_file' => 'public/uploads/demo/products/'.$key.'.svg',
                    'downloaded_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'placeholder' => true,
                ];
                ++$placeholders;
            }
        }

        ksort($manifest);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($manifestPath, $json."\n", LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d’écrire le manifeste des images.');
        }

        return compact('downloaded', 'existing', 'placeholders') + ['manifest' => $manifestPath];
    }

    /** @return array<string, array<string, mixed>> */
    private function readManifest(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        try {
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($manifest) ? $manifest : [];
        } catch (\JsonException) {
            return [];
        }
    }

    private function placeholderSvg(string $name): string
    {
        $safeName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
              <rect width="800" height="800" fill="#f1f5f9"/>
              <rect x="180" y="170" width="440" height="360" rx="32" fill="#dbe4ee"/>
              <circle cx="320" cy="300" r="52" fill="#94a3b8"/>
              <path d="M220 480l120-120 90 90 70-70 80 100H220z" fill="#64748b"/>
              <text x="400" y="620" text-anchor="middle" font-family="Arial,sans-serif" font-size="30" fill="#334155">{$safeName}</text>
              <text x="400" y="665" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" fill="#64748b">Image de démonstration</text>
            </svg>
            SVG;
    }
}
