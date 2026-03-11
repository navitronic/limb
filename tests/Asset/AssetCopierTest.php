<?php

declare(strict_types=1);

namespace Limb\Tests\Asset;

use Limb\Asset\AssetCopier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AssetCopierTest extends TestCase
{
    private string $sourceDir;
    private string $destDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().'/limb_asset_test_'.bin2hex(random_bytes(4));
        $this->sourceDir = $base.'/source';
        $this->destDir = $base.'/dest';
        mkdir($this->sourceDir, 0o777, true);
        mkdir($this->destDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(\dirname($this->sourceDir));
    }

    #[Test]
    public function itCopiesStaticAssets(): void
    {
        mkdir($this->sourceDir.'/assets', 0o777, true);
        file_put_contents($this->sourceDir.'/assets/style.css', 'body { color: red; }');

        $copier = new AssetCopier();
        $count = $copier->copy(
            [$this->sourceDir.'/assets/style.css'],
            $this->sourceDir,
            $this->destDir,
        );

        self::assertSame(1, $count);
        self::assertFileExists($this->destDir.'/assets/style.css');
        self::assertSame('body { color: red; }', file_get_contents($this->destDir.'/assets/style.css'));
    }

    #[Test]
    public function itPreservesDirectoryStructure(): void
    {
        mkdir($this->sourceDir.'/assets/images', 0o777, true);
        file_put_contents($this->sourceDir.'/assets/images/logo.png', 'PNG_DATA');

        $copier = new AssetCopier();
        $copier->copy(
            [$this->sourceDir.'/assets/images/logo.png'],
            $this->sourceDir,
            $this->destDir,
        );

        self::assertFileExists($this->destDir.'/assets/images/logo.png');
        self::assertSame('PNG_DATA', file_get_contents($this->destDir.'/assets/images/logo.png'));
    }

    #[Test]
    public function itCopiesMultipleFiles(): void
    {
        mkdir($this->sourceDir.'/css', 0o777, true);
        mkdir($this->sourceDir.'/js', 0o777, true);
        file_put_contents($this->sourceDir.'/css/main.css', 'css');
        file_put_contents($this->sourceDir.'/js/app.js', 'js');

        $copier = new AssetCopier();
        $count = $copier->copy(
            [
                $this->sourceDir.'/css/main.css',
                $this->sourceDir.'/js/app.js',
            ],
            $this->sourceDir,
            $this->destDir,
        );

        self::assertSame(2, $count);
    }

    #[Test]
    public function itReturnsZeroForEmptyList(): void
    {
        $copier = new AssetCopier();
        $count = $copier->copy([], $this->sourceDir, $this->destDir);

        self::assertSame(0, $count);
    }

    #[Test]
    public function itSkipsMissingSourceFiles(): void
    {
        $copier = new AssetCopier();
        $count = $copier->copy(
            [$this->sourceDir.'/nonexistent.css'],
            $this->sourceDir,
            $this->destDir,
        );

        self::assertSame(0, $count);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
