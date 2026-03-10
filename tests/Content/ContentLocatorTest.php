<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Config\SiteConfig;
use App\Content\ContentClassification;
use App\Content\ContentLocator;
use App\Content\ScanResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentLocatorTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/basic-site';
    }

    private function createConfig(string $source = '', string $destination = '_site'): SiteConfig
    {
        return new SiteConfig(
            source: '' === $source ? $this->fixtureDir : $source,
            destination: $destination,
        );
    }

    #[Test]
    public function itReturnsScanResult(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        self::assertInstanceOf(ScanResult::class, $result);
    }

    #[Test]
    public function itDetectsPosts(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $posts = $result->getByClassification(ContentClassification::Post);
        self::assertCount(1, $posts);
        self::assertStringContainsString('hello-world.md', $posts[0]);
    }

    #[Test]
    public function itDetectsLayouts(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $layouts = $result->getByClassification(ContentClassification::Layout);
        self::assertCount(2, $layouts);
        $layoutNames = array_map(static fn (string $path): string => basename($path), $layouts);
        sort($layoutNames);
        self::assertSame(['default.html.twig', 'post.html.twig'], $layoutNames);
    }

    #[Test]
    public function itDetectsIncludes(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $includes = $result->getByClassification(ContentClassification::Include);
        self::assertCount(1, $includes);
        self::assertStringContainsString('header.html.twig', $includes[0]);
    }

    #[Test]
    public function itDetectsDataFiles(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $data = $result->getByClassification(ContentClassification::Data);
        self::assertCount(3, $data);
        $dataNames = array_map(static fn (string $path): string => basename($path), $data);
        sort($dataNames);
        self::assertContains('navigation.yml', $dataNames);
    }

    #[Test]
    public function itDetectsPages(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $pages = $result->getByClassification(ContentClassification::Page);
        self::assertCount(2, $pages);

        $filenames = array_map(static fn (string $path): string => basename($path), $pages);
        sort($filenames);
        self::assertSame(['about.md', 'index.md'], $filenames);
    }

    #[Test]
    public function itDetectsStaticAssets(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $static = $result->getByClassification(ContentClassification::Static);
        self::assertCount(1, $static);
        self::assertStringContainsString('style.css', $static[0]);
    }

    #[Test]
    public function itIgnoresSiteOutputDirectory(): void
    {
        // Create a _site directory in the fixture to ensure it's ignored
        $siteDir = $this->fixtureDir.'/_site';
        if (!is_dir($siteDir)) {
            mkdir($siteDir, 0o777, true);
            file_put_contents($siteDir.'/output.html', '<html></html>');
        }

        try {
            $locator = new ContentLocator();
            $result = $locator->scan($this->createConfig());

            $all = $result->all();
            foreach ($all as $path) {
                self::assertStringNotContainsString('_site', $path);
            }
        } finally {
            if (file_exists($siteDir.'/output.html')) {
                unlink($siteDir.'/output.html');
            }
            if (is_dir($siteDir)) {
                rmdir($siteDir);
            }
        }
    }

    #[Test]
    public function itIgnoresConfigFile(): void
    {
        $locator = new ContentLocator();
        $result = $locator->scan($this->createConfig());

        $all = $result->all();
        foreach ($all as $path) {
            self::assertStringNotContainsString('_config.yml', basename($path));
        }
    }

    #[Test]
    public function itRespectsExcludeConfig(): void
    {
        $config = new SiteConfig(
            source: $this->fixtureDir,
            exclude: ['assets'],
        );

        $locator = new ContentLocator();
        $result = $locator->scan($config);

        $static = $result->getByClassification(ContentClassification::Static);
        self::assertCount(0, $static);
    }

    #[Test]
    public function itIgnoresDotfiles(): void
    {
        $dotfile = $this->fixtureDir.'/.hidden';
        file_put_contents($dotfile, 'hidden');

        try {
            $locator = new ContentLocator();
            $result = $locator->scan($this->createConfig());

            $all = $result->all();
            foreach ($all as $path) {
                self::assertStringNotContainsString('.hidden', basename($path));
            }
        } finally {
            if (file_exists($dotfile)) {
                unlink($dotfile);
            }
        }
    }
}
