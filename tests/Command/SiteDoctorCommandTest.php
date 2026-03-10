<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SiteDoctorCommand;
use App\Config\ConfigLoader;
use App\Config\ConfigMerger;
use App\Content\ContentLocator;
use App\FrontMatter\FrontMatterParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SiteDoctorCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/limb_doctor_test_'.bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function itPassesOnHealthySite(): void
    {
        $this->createHealthySite();

        $tester = $this->runDoctor();

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('OK', $display);
    }

    #[Test]
    public function itDetectsMissingConfig(): void
    {
        // Empty dir — no _config.yml
        $tester = $this->runDoctor();

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('_config.yml', $tester->getDisplay());
    }

    #[Test]
    public function itDetectsMissingLayout(): void
    {
        $this->createHealthySite();

        // Add a page referencing a non-existent layout
        file_put_contents($this->tempDir.'/broken.md', "---\ntitle: Broken\nlayout: nonexistent\n---\nContent");

        $tester = $this->runDoctor();

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('nonexistent', $tester->getDisplay());
    }

    #[Test]
    public function itDetectsBadPostFilename(): void
    {
        $this->createHealthySite();

        mkdir($this->tempDir.'/_posts', 0o777, true);
        file_put_contents($this->tempDir.'/_posts/bad-filename.md', "---\ntitle: Bad\nlayout: default\n---\nContent");

        $tester = $this->runDoctor();

        $display = $tester->getDisplay();
        self::assertStringContainsString('bad-filename.md', $display);
    }

    #[Test]
    public function itReportsWarningForMissingInclude(): void
    {
        $this->createHealthySite();

        // Layout references a non-existent include
        file_put_contents(
            $this->tempDir.'/_layouts/default.html.twig',
            "{{ content|raw }}\n{% include '@includes/missing.html.twig' %}",
        );

        $tester = $this->runDoctor();

        $display = $tester->getDisplay();
        self::assertStringContainsString('missing.html.twig', $display);
    }

    #[Test]
    public function itValidatesPermalinkTokens(): void
    {
        $this->createHealthySite();

        // Write config with invalid permalink token
        file_put_contents($this->tempDir.'/_config.yml', "title: Test\nurl: http://localhost\npermalink: /:year/:invalid_token/:title/\n");

        $tester = $this->runDoctor();

        $display = $tester->getDisplay();
        self::assertStringContainsString('invalid_token', $display);
    }

    private function runDoctor(): CommandTester
    {
        $command = new SiteDoctorCommand(
            new ConfigLoader(),
            new ConfigMerger(),
            new ContentLocator(),
            new FrontMatterParser(),
        );
        $tester = new CommandTester($command);
        $tester->execute(['--source' => $this->tempDir]);

        return $tester;
    }

    private function createHealthySite(): void
    {
        file_put_contents($this->tempDir.'/_config.yml', "title: Test Site\nurl: http://localhost\n");

        mkdir($this->tempDir.'/_layouts', 0o777, true);
        file_put_contents($this->tempDir.'/_layouts/default.html.twig', '{{ content|raw }}');

        mkdir($this->tempDir.'/_includes', 0o777, true);

        file_put_contents($this->tempDir.'/index.md', "---\ntitle: Home\nlayout: default\n---\nWelcome");
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
