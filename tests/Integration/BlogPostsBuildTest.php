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

final class BlogPostsBuildTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/blog-posts';
        $this->outputDir = sys_get_temp_dir().'/limb_blog_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itRendersMultiplePostsAtDateBasedPermalinks(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $firstPath = $this->outputDir.'/2026/01/10/first-post/index.html';
        self::assertFileExists($firstPath);
        $firstHtml = (string) file_get_contents($firstPath);
        self::assertStringContainsString('First Post', $firstHtml);
        self::assertStringContainsString('first blog post', $firstHtml);

        $secondPath = $this->outputDir.'/2026/02/14/second-post/index.html';
        self::assertFileExists($secondPath);
        $secondHtml = (string) file_get_contents($secondPath);
        self::assertStringContainsString('Second Post', $secondHtml);
        self::assertStringContainsString('<strong>bold text</strong>', $secondHtml);

        $thirdPath = $this->outputDir.'/2026/03/20/third-post/index.html';
        self::assertFileExists($thirdPath);
        $thirdHtml = (string) file_get_contents($thirdPath);
        self::assertStringContainsString('Third Post', $thirdHtml);
    }

    #[Test]
    public function itWrapsPostsInLayoutChain(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $postHtml = (string) file_get_contents($this->outputDir.'/2026/01/10/first-post/index.html');

        // Post layout wraps in <article> with <h1>
        self::assertStringContainsString('<article>', $postHtml);
        self::assertStringContainsString('<h1>First Post</h1>', $postHtml);

        // Default layout wraps in <!DOCTYPE html>
        self::assertStringContainsString('<!DOCTYPE html>', $postHtml);
        self::assertStringContainsString('<title>First Post | Blog Test</title>', $postHtml);
    }

    #[Test]
    public function itReturnsBuildResultWithCorrectPostCount(): void
    {
        $runner = $this->createBuildRunner();
        $result = $runner->build($this->fixtureDir, $this->outputDir);

        self::assertSame(1, $result->pagesRendered);
        self::assertSame(3, $result->postsRendered);
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
