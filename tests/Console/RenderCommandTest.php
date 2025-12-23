<?php

declare(strict_types=1);

namespace Limb\Tests\Console;

use Limb\Container\LimbContainerFactory;
use Limb\Console\Command\RenderCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RenderCommandTest extends TestCase
{
    public function testExecuteRendersMarkdownFile(): void
    {
        $container = LimbContainerFactory::create();
        $command = $container->get(RenderCommand::class);
        $tester = new CommandTester($command);
        $path = \tempnam(\sys_get_temp_dir(), 'limb_');

        $this->assertNotFalse($path, 'Failed to create temporary file.');

        $markdown = "# Hello\n\nBody";
        $contentsWritten = \file_put_contents($path, $markdown);

        $this->assertNotFalse($contentsWritten, 'Failed to write temporary markdown.');

        try {
            $status = $tester->execute(['path' => $path]);

            $this->assertSame(Command::SUCCESS, $status);
            $this->assertStringContainsString('<h1>', $tester->getDisplay());
            $this->assertStringContainsString('Hello', $tester->getDisplay());
        } finally {
            \unlink($path);
        }
    }

    public function testExecuteReportsMissingFile(): void
    {
        $container = LimbContainerFactory::create();
        $command = $container->get(RenderCommand::class);
        $tester = new CommandTester($command);
        $missingPath = '/tmp/limb-missing-file-' . \uniqid('', true) . '.md';

        $status = $tester->execute(['path' => $missingPath]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Unable to read markdown file', $tester->getDisplay());
    }
}
