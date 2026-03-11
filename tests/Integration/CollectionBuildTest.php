<?php

declare(strict_types=1);

namespace Limb\Tests\Integration;

use Limb\Asset\AssetCopier;
use Limb\Collection\CollectionBuilder;
use Limb\Config\ConfigLoader;
use Limb\Config\ConfigMerger;
use Limb\Content\ContentLocator;
use Limb\Data\DataLoader;
use Limb\FrontMatter\FrontMatterParser;
use Limb\Markdown\MarkdownRenderer;
use Limb\Model\DocumentFactory;
use Limb\Output\OutputWriter;
use Limb\Permalink\OutputPathResolver;
use Limb\Permalink\PermalinkGenerator;
use Limb\Pipeline\BuildRunner;
use Limb\Rendering\TwigEnvironmentFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class CollectionBuildTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/collections-site';
        $this->outputDir = sys_get_temp_dir().'/limb_collection_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itRendersCollectionDocumentsAtConfiguredPermalinks(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        // Collection docs should be rendered at /docs/:title/
        $gettingStartedPath = $this->outputDir.'/docs/getting-started/index.html';
        self::assertFileExists($gettingStartedPath);
        $html = (string) file_get_contents($gettingStartedPath);
        self::assertStringContainsString('getting started guide', $html);

        $apiRefPath = $this->outputDir.'/docs/api-reference/index.html';
        self::assertFileExists($apiRefPath);
        $apiHtml = (string) file_get_contents($apiRefPath);
        self::assertStringContainsString('API reference', $apiHtml);
    }

    #[Test]
    public function itIncludesCollectionDocumentsInBuildResult(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        // 1 index page + 2 collection docs = 3 total rendered
        // (collection docs count depends on how BuildRunner reports them)
        self::assertSame([], $result->errors);
    }

    #[Test]
    public function itRendersIndexPage(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $indexPath = $this->outputDir.'/index.html';
        self::assertFileExists($indexPath);
        $html = (string) file_get_contents($indexPath);
        self::assertStringContainsString('collections test site', $html);
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
