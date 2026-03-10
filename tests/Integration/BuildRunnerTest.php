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

final class BuildRunnerTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/basic-site';
        $this->outputDir = sys_get_temp_dir().'/limb_build_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itBuildsCompletesite(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        // Index page exists with expected content
        $indexPath = $this->outputDir.'/index.html';
        self::assertFileExists($indexPath);
        $indexHtml = (string) file_get_contents($indexPath);
        self::assertStringContainsString('<!DOCTYPE html>', $indexHtml);
        self::assertStringContainsString('<title>Home | Test Site</title>', $indexHtml);
        self::assertStringContainsString('Welcome to the site.', $indexHtml);
    }

    #[Test]
    public function itCopiesStaticAssets(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        self::assertFileExists($this->outputDir.'/assets/style.css');
    }

    #[Test]
    public function itOutputsPostsAtCorrectPermalinkPath(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $postPath = $this->outputDir.'/2026/01/15/hello-world/index.html';
        self::assertFileExists($postPath);
        $postHtml = (string) file_get_contents($postPath);
        self::assertStringContainsString('Hello World', $postHtml);
        self::assertStringContainsString('This is my first post.', $postHtml);
    }

    #[Test]
    public function itReturnsBuildResultWithCorrectCounts(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        self::assertSame(2, $result->pagesRendered);
        self::assertSame(1, $result->postsRendered);
        self::assertSame(1, $result->staticFilesCopied);
        self::assertSame([], $result->errors);
        self::assertGreaterThan(0.0, $result->elapsedTime);
    }

    #[Test]
    public function itRendersAboutPage(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $aboutPath = $this->outputDir.'/about/index.html';
        self::assertFileExists($aboutPath);
        $aboutHtml = (string) file_get_contents($aboutPath);
        self::assertStringContainsString('About this site.', $aboutHtml);
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
