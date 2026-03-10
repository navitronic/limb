<?php

declare(strict_types=1);

namespace App\Tests\Data;

use App\Data\DataLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DataLoaderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/basic-site';
    }

    #[Test]
    public function itLoadsYamlFile(): void
    {
        $loader = new DataLoader();
        $data = $loader->load($this->fixtureDir.'/_data');

        self::assertArrayHasKey('navigation', $data);
        self::assertIsArray($data['navigation']);
        self::assertCount(2, $data['navigation']);
        self::assertSame('Home', $data['navigation'][0]['label']);
    }

    #[Test]
    public function itLoadsJsonFile(): void
    {
        $loader = new DataLoader();
        $data = $loader->load($this->fixtureDir.'/_data');

        self::assertArrayHasKey('settings', $data);
        self::assertSame('1.0', $data['settings']['version']);
        self::assertSame(['search', 'tags'], $data['settings']['features']);
    }

    #[Test]
    public function itLoadsNestedDirectories(): void
    {
        $loader = new DataLoader();
        $data = $loader->load($this->fixtureDir.'/_data');

        self::assertArrayHasKey('authors', $data);
        self::assertIsArray($data['authors']);
        self::assertArrayHasKey('team', $data['authors']);
        self::assertCount(2, $data['authors']['team']);
        self::assertSame('Alice', $data['authors']['team'][0]['name']);
    }

    #[Test]
    public function itLoadsMultipleFiles(): void
    {
        $loader = new DataLoader();
        $data = $loader->load($this->fixtureDir.'/_data');

        self::assertArrayHasKey('navigation', $data);
        self::assertArrayHasKey('settings', $data);
        self::assertArrayHasKey('authors', $data);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenDirectoryMissing(): void
    {
        $loader = new DataLoader();
        $data = $loader->load($this->fixtureDir.'/_data_nonexistent');

        self::assertSame([], $data);
    }

    #[Test]
    public function itThrowsOnInvalidYaml(): void
    {
        $tmpDir = sys_get_temp_dir().'/limb_data_test_'.bin2hex(random_bytes(4));
        mkdir($tmpDir, 0o777, true);
        file_put_contents($tmpDir.'/broken.yml', "title: \"unclosed\ninvalid:\n  - {\n");

        try {
            $loader = new DataLoader();
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/Failed to parse data file/');
            $loader->load($tmpDir);
        } finally {
            unlink($tmpDir.'/broken.yml');
            rmdir($tmpDir);
        }
    }

    #[Test]
    public function itThrowsOnInvalidJson(): void
    {
        $tmpDir = sys_get_temp_dir().'/limb_data_test_'.bin2hex(random_bytes(4));
        mkdir($tmpDir, 0o777, true);
        file_put_contents($tmpDir.'/broken.json', '{invalid json}');

        try {
            $loader = new DataLoader();
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/Failed to parse data file/');
            $loader->load($tmpDir);
        } finally {
            unlink($tmpDir.'/broken.json');
            rmdir($tmpDir);
        }
    }
}
