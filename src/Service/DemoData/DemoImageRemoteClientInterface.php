<?php

namespace App\Service\DemoData;

interface DemoImageRemoteClientInterface
{
    public function isConfigured(): bool;

    /** @return array{id: int, url: string, photo_url: string, photographer: string}|null */
    public function search(string $query): ?array;

    public function download(string $url): string;
}
