<?php

namespace App\Tests\DemoData;

use App\Service\DemoData\DemoCatalog;
use App\Service\DemoData\DemoImageDownloader;
use App\Service\DemoData\DemoImageRemoteClientInterface;
use PHPUnit\Framework\TestCase;

final class DemoImageDownloaderTest extends TestCase
{
    public function testFonctionneSansCleEtSansAppelReseau(): void
    {
        $remote = $this->createMock(DemoImageRemoteClientInterface::class);
        $remote->expects(self::once())->method('isConfigured')->willReturn(false);
        $remote->expects(self::never())->method('search');
        $remote->expects(self::never())->method('download');
        $directory = sys_get_temp_dir().'/comdely-demo-images-'.bin2hex(random_bytes(6));
        $catalog = new DemoCatalog(dirname(__DIR__, 2).'/config/demo/catalog.yaml');
        $downloader = new DemoImageDownloader($catalog, $remote, $directory);

        try {
            $result = $downloader->download();
            self::assertSame(0, $result['downloaded']);
            self::assertSame(40, $result['placeholders']);
            self::assertFileExists($result['manifest']);
            self::assertCount(40, json_decode((string) file_get_contents($result['manifest']), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory) as $file) {
            unlink($file->getPathname());
        }
        rmdir($directory);
    }
}
