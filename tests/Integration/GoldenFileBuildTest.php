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

final class GoldenFileBuildTest extends TestCase
{
    private string $sourceDir;
    private string $expectedDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->sourceDir = \dirname(__DIR__).'/Fixtures/golden/source';
        $this->expectedDir = \dirname(__DIR__).'/Fixtures/golden/expected_output';
        $this->outputDir = sys_get_temp_dir().'/limb_golden_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function itProducesExpectedOutputFiles(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->sourceDir, $this->outputDir);

        $expectedFiles = $this->listFiles($this->expectedDir);
        $actualFiles = $this->listFiles($this->outputDir);

        self::assertSame($expectedFiles, $actualFiles, 'Output file list does not match expected');
    }

    #[Test]
    public function itProducesExpectedOutputContent(): void
    {
        $runner = $this->createBuildRunner();
        $runner->build($this->sourceDir, $this->outputDir);

        $expectedFiles = $this->listFiles($this->expectedDir);

        foreach ($expectedFiles as $relativePath) {
            $expectedContent = file_get_contents($this->expectedDir.'/'.$relativePath);
            $actualContent = file_get_contents($this->outputDir.'/'.$relativePath);

            self::assertSame(
                $expectedContent,
                $actualContent,
                \sprintf('Content mismatch for file: %s', $relativePath),
            );
        }
    }

    /**
     * List all files in a directory, returning sorted relative paths.
     *
     * @return list<string>
     */
    private function listFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $prefix = rtrim($dir, '/').'/';

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = substr($file->getPathname(), \strlen($prefix));
            }
        }

        sort($files);

        return $files;
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
