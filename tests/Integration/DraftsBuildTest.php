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

final class DraftsBuildTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/drafts';
        $this->outputDir = sys_get_temp_dir().'/limb_drafts_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itExcludesDraftsByDefault(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        // Published post should exist
        self::assertFileExists($this->outputDir.'/2026/01/01/published/index.html');

        // Draft should NOT be rendered (no date-based path, and slug-based shouldn't exist)
        self::assertSame(1, $result->postsRendered);
    }

    #[Test]
    public function itIncludesDraftsWhenFlagIsSet(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build(
            $this->fixtureDir,
            $this->outputDir,
            includeDrafts: true,
        );

        // Published post should exist
        self::assertFileExists($this->outputDir.'/2026/01/01/published/index.html');

        // Draft should also be rendered (posts count includes drafts)
        self::assertSame(2, $result->postsRendered);
    }

    #[Test]
    public function itRendersPublishedPostContent(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $html = (string) file_get_contents($this->outputDir.'/2026/01/01/published/index.html');
        self::assertStringContainsString('published post', $html);
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
