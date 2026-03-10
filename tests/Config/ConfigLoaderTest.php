<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\ConfigLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir().'/limb_config_test_'.bin2hex(random_bytes(4));
        mkdir($this->fixtureDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    #[Test]
    public function itLoadsValidYaml(): void
    {
        file_put_contents($this->fixtureDir.'/_config.yml', "title: \"My Site\"\nbaseUrl: \"https://example.com\"\n");

        $loader = new ConfigLoader();
        $result = $loader->load($this->fixtureDir);

        self::assertIsArray($result);
        self::assertSame('My Site', $result['title']);
        self::assertSame('https://example.com', $result['baseUrl']);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenConfigFileMissing(): void
    {
        $loader = new ConfigLoader();
        $result = $loader->load($this->fixtureDir);

        self::assertSame([], $result);
    }

    #[Test]
    public function itThrowsOnInvalidYaml(): void
    {
        file_put_contents($this->fixtureDir.'/_config.yml', "title: \"unclosed\ninvalid:\n  - {\n");

        $loader = new ConfigLoader();

        $this->expectException(\App\Exception\ConfigException::class);
        $this->expectExceptionMessageMatches('/Failed to parse config/');
        $loader->load($this->fixtureDir);
    }

    #[Test]
    public function itLoadsFromCustomConfigPath(): void
    {
        file_put_contents($this->fixtureDir.'/custom.yml', "title: \"Custom\"\n");

        $loader = new ConfigLoader();
        $result = $loader->load($this->fixtureDir, $this->fixtureDir.'/custom.yml');

        self::assertSame('Custom', $result['title']);
    }

    #[Test]
    public function itThrowsWhenExplicitConfigPathDoesNotExist(): void
    {
        $loader = new ConfigLoader();

        $this->expectException(\App\Exception\ConfigException::class);
        $this->expectExceptionMessageMatches('/Config file not found/');
        $loader->load($this->fixtureDir, $this->fixtureDir.'/nonexistent.yml');
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
