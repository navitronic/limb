<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Asset\AssetCopier;
use App\Collection\CollectionBuilder;
use App\Config\ConfigLoader;
use App\Config\ConfigMerger;
use App\Content\ContentLocator;
use App\Data\DataLoader;
use App\FrontMatter\FrontMatterParser;
use App\Markdown\MarkdownRenderer;
use App\Model\DocumentFactory;
use App\Output\OutputWriter;
use App\Permalink\OutputPathResolver;
use App\Permalink\PermalinkGenerator;
use App\Pipeline\BuildRunner;
use App\Rendering\TwigEnvironmentFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class NestedLayoutsBuildTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/nested-layouts';
        $this->outputDir = sys_get_temp_dir().'/limb_nested_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itRendersTwoLevelLayoutChain(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $indexHtml = (string) file_get_contents($this->outputDir.'/index.html');

        // Content from base layout
        self::assertStringContainsString('<!DOCTYPE html>', $indexHtml);
        self::assertStringContainsString('class="base"', $indexHtml);

        // Content from default layout
        self::assertStringContainsString('class="default-wrapper"', $indexHtml);

        // Actual page content
        self::assertStringContainsString('two-level layout chain', $indexHtml);
    }

    #[Test]
    public function itRendersThreeLevelLayoutChain(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $postPath = $this->outputDir.'/2026/01/01/deep-post/index.html';
        self::assertFileExists($postPath);
        $postHtml = (string) file_get_contents($postPath);

        // Content from base layout
        self::assertStringContainsString('<!DOCTYPE html>', $postHtml);
        self::assertStringContainsString('class="base"', $postHtml);

        // Content from default layout
        self::assertStringContainsString('class="default-wrapper"', $postHtml);

        // Content from post layout
        self::assertStringContainsString('class="post-wrapper"', $postHtml);
        self::assertStringContainsString('<h1>Deep Nesting</h1>', $postHtml);

        // Actual page content
        self::assertStringContainsString('three-level layout chain', $postHtml);
    }

    #[Test]
    public function itBuildsWithNoErrors(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        self::assertSame([], $result->errors);
    }

    private function createBuildRunner(): BuildRunner
    {
        return new BuildRunner(
            configLoader: new ConfigLoader(),
            configMerger: new ConfigMerger(),
            contentLocator: new ContentLocator(),
            frontMatterParser: new FrontMatterParser(),
            documentFactory: new DocumentFactory(),
            dataLoader: new DataLoader(),
            collectionBuilder: new CollectionBuilder(),
            permalinkGenerator: new PermalinkGenerator(),
            outputPathResolver: new OutputPathResolver(),
            twigEnvironmentFactory: new TwigEnvironmentFactory(),
            markdownRenderer: new MarkdownRenderer(),
            outputWriter: new OutputWriter(),
            assetCopier: new AssetCopier(),
            eventDispatcher: new EventDispatcher(),
        );
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
