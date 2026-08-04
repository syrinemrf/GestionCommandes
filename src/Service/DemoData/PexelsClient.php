<?php

namespace App\Service\DemoData;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PexelsClient implements DemoImageRemoteClientInterface
{
    public function __construct(
        #[Autowire('%env(PEXELS_API_KEY)%')]
        private string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function search(string $query): ?array
    {
        $payload = $this->request(
            'https://api.pexels.com/v1/search?'.http_build_query([
                'query' => $query,
                'per_page' => 1,
                'orientation' => 'square',
            ]),
            true,
        );
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $photo = $data['photos'][0] ?? null;

        if (!is_array($photo) || !isset($photo['id'], $photo['src']['large'], $photo['url'])) {
            return null;
        }

        return [
            'id' => (int) $photo['id'],
            'url' => (string) $photo['src']['large'],
            'photo_url' => (string) $photo['url'],
            'photographer' => (string) ($photo['photographer'] ?? 'Pexels'),
        ];
    }

    public function download(string $url): string
    {
        return $this->request($url, false);
    }

    private function request(string $url, bool $authenticated): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Impossible d’initialiser la connexion HTTP.');
        }
        $headers = $authenticated ? ['Authorization: '.$this->apiKey] : [];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'ComdelyDemoData/1.0',
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new \RuntimeException($error !== '' ? $error : sprintf('Pexels a répondu avec le statut %d.', $status));
        }

        return $body;
    }
}
