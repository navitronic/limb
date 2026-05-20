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

final class ArchiveBuildTest extends TestCase
{
    private string $fixtureDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/archives';
        $this->outputDir = sys_get_temp_dir().'/limb_archive_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itGeneratesYearArchivePages(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $year2026 = $this->outputDir.'/2026/index.html';
        self::assertFileExists($year2026);
        $html = (string) file_get_contents($year2026);
        self::assertStringContainsString('Archive: 2026', $html);
        self::assertStringContainsString('January Post', $html);
        self::assertStringContainsString('March Post', $html);

        $year2025 = $this->outputDir.'/2025/index.html';
        self::assertFileExists($year2025);
        $html2025 = (string) file_get_contents($year2025);
        self::assertStringContainsString('Archive: 2025', $html2025);
        self::assertStringContainsString('Old Post', $html2025);
    }

    #[Test]
    public function itGeneratesMonthArchivePages(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $jan2026 = $this->outputDir.'/2026/01/index.html';
        self::assertFileExists($jan2026);
        $html = (string) file_get_contents($jan2026);
        self::assertStringContainsString('Archive: 2026/01', $html);
        self::assertStringContainsString('January Post', $html);
        self::assertStringNotContainsString('March Post', $html);

        $mar2026 = $this->outputDir.'/2026/03/index.html';
        self::assertFileExists($mar2026);
        $marHtml = (string) file_get_contents($mar2026);
        self::assertStringContainsString('March Post', $marHtml);
        self::assertStringNotContainsString('January Post', $marHtml);
    }

    #[Test]
    public function itDoesNotConflictWithPostPermalinks(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        // Posts still render at their date-based URLs
        self::assertFileExists($this->outputDir.'/2026/01/10/january-post/index.html');
        self::assertFileExists($this->outputDir.'/2026/03/15/march-post/index.html');
        self::assertFileExists($this->outputDir.'/2025/06/20/old-post/index.html');

        // Archives exist alongside them
        self::assertFileExists($this->outputDir.'/2026/index.html');
        self::assertFileExists($this->outputDir.'/2026/01/index.html');
    }

    #[Test]
    public function yearArchiveDoesNotContainPostsFromOtherYears(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->fixtureDir, $this->outputDir);

        $html2026 = (string) file_get_contents($this->outputDir.'/2026/index.html');
        self::assertStringNotContainsString('Old Post', $html2026);

        $html2025 = (string) file_get_contents($this->outputDir.'/2025/index.html');
        self::assertStringNotContainsString('January Post', $html2025);
        self::assertStringNotContainsString('March Post', $html2025);
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
