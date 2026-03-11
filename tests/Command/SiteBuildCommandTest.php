<?php

declare(strict_types=1);

namespace Limb\Tests\Command;

use Limb\Asset\AssetCopier;
use Limb\Collection\CollectionBuilder;
use Limb\Command\SiteBuildCommand;
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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class SiteBuildCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/limb_build_cmd_test_'.bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function itDisplaysFormattedErrorOnInvalidConfig(): void
    {
        file_put_contents($this->tempDir.'/_config.yml', "title: \"unclosed\ninvalid:\n  - {\n");

        $command = new SiteBuildCommand($this->createBuildRunner());
        $tester = new CommandTester($command);
        $tester->execute(['--source' => $this->tempDir]);

        self::assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Failed to parse config', $display);
        self::assertStringNotContainsString('#0', $display);
    }

    #[Test]
    public function itDisplaysFormattedErrorOnMissingExplicitConfig(): void
    {
        $command = new SiteBuildCommand($this->createBuildRunner());
        $tester = new CommandTester($command);
        $tester->execute(['--source' => $this->tempDir, '--config' => $this->tempDir.'/nonexistent.yml']);

        self::assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Config file not found', $display);
    }

    #[Test]
    public function itShowsStackTraceInVeryVerboseMode(): void
    {
        file_put_contents($this->tempDir.'/_config.yml', "title: \"unclosed\ninvalid:\n  - {\n");

        $command = new SiteBuildCommand($this->createBuildRunner());
        $tester = new CommandTester($command);
        $tester->execute(
            ['--source' => $this->tempDir],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
        );

        self::assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Failed to parse config', $display);
        self::assertStringContainsString('ConfigException', $display);
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
