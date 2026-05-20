<?php

declare(strict_types=1);

namespace Limb\Tests\Integration;

use Limb\Archive\ArchiveGenerator;
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

final class PermalinkOverridesBuildTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/permalink-overrides';
        $this->outputDir = sys_get_temp_dir().'/limb_permalink_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itUsesPermalinkOverrideForPages(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $customPath = $this->outputDir.'/custom/path.html';
        self::assertFileExists($customPath);
        $html = (string) file_get_contents($customPath);
        self::assertStringContainsString('custom permalink', $html);
    }

    #[Test]
    public function itUsesPermalinkOverrideForPosts(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        // Post should use front matter permalink instead of date-based
        $overriddenPath = $this->outputDir.'/blog/my-custom-url/index.html';
        self::assertFileExists($overriddenPath);
        $html = (string) file_get_contents($overriddenPath);
        self::assertStringContainsString('permalink override', $html);

        // Original date-based path should NOT exist
        self::assertFileDoesNotExist($this->outputDir.'/2026/05/01/original-slug/index.html');
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
            archiveGenerator: new ArchiveGenerator(),
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
