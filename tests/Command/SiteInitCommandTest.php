<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SiteInitCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SiteInitCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/limb_init_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function itCreatesScaffoldStructure(): void
    {
        $command = new SiteInitCommand();
        $tester = new CommandTester($command);
        $tester->execute(['path' => $this->tempDir]);

        self::assertSame(0, $tester->getStatusCode());

        // Config
        self::assertFileExists($this->tempDir.'/_config.yml');

        // Layouts
        self::assertFileExists($this->tempDir.'/_layouts/default.html.twig');
        self::assertFileExists($this->tempDir.'/_layouts/post.html.twig');

        // Includes
        self::assertFileExists($this->tempDir.'/_includes/header.html.twig');
        self::assertFileExists($this->tempDir.'/_includes/footer.html.twig');

        // Posts
        $postFiles = glob($this->tempDir.'/_posts/*.md');
        self::assertNotFalse($postFiles);
        self::assertCount(1, $postFiles);

        // Data
        self::assertFileExists($this->tempDir.'/_data/navigation.yml');

        // Assets
        self::assertFileExists($this->tempDir.'/assets/css/style.css');

        // Pages
        self::assertFileExists($this->tempDir.'/index.md');
        self::assertFileExists($this->tempDir.'/about.md');
    }

    #[Test]
    public function itRefusesToOverwriteExistingSite(): void
    {
        mkdir($this->tempDir, 0o777, true);
        file_put_contents($this->tempDir.'/_config.yml', 'title: Existing');

        $command = new SiteInitCommand();
        $tester = new CommandTester($command);
        $tester->execute(['path' => $this->tempDir]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('already contains', $tester->getDisplay());
    }

    #[Test]
    public function itOutputsCreatedFiles(): void
    {
        $command = new SiteInitCommand();
        $tester = new CommandTester($command);
        $tester->execute(['path' => $this->tempDir]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('_config.yml', $display);
        self::assertStringContainsString('_layouts/default.html.twig', $display);
    }

    #[Test]
    public function scaffoldConfigContainsExpectedValues(): void
    {
        $command = new SiteInitCommand();
        $tester = new CommandTester($command);
        $tester->execute(['path' => $this->tempDir]);

        $config = (string) file_get_contents($this->tempDir.'/_config.yml');
        self::assertStringContainsString('title:', $config);
        self::assertStringContainsString('url:', $config);
    }

    #[Test]
    public function postLayoutExtendsDefault(): void
    {
        $command = new SiteInitCommand();
        $tester = new CommandTester($command);
        $tester->execute(['path' => $this->tempDir]);

        $postLayout = (string) file_get_contents($this->tempDir.'/_layouts/post.html.twig');
        self::assertStringContainsString('layout: default', $postLayout);
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
