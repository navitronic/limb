<?php

declare(strict_types=1);

namespace Limb\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'site:init',
    description: 'Scaffold a new site directory with example content',
)]
class SiteInitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Directory to create the site in');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        \assert(\is_string($path));
        $path = rtrim($path, '/');

        if (is_file($path.'/_config.yml')) {
            $io->error(\sprintf('"%s" already contains a _config.yml. Refusing to overwrite.', $path));

            return Command::FAILURE;
        }

        $scaffoldDir = $this->getScaffoldDir();
        $filesystem = new Filesystem();
        $createdFiles = [];

        $finder = new Finder();
        $finder->files()->in($scaffoldDir);

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $destPath = $path.'/'.$relativePath;

            // Special case: rename the welcome post with today's date
            if (str_starts_with($relativePath, '_posts/') && 'welcome.md' === $file->getFilename()) {
                $relativePath = '_posts/'.(new \DateTimeImmutable())->format('Y-m-d').'-welcome.md';
                $destPath = $path.'/'.$relativePath;
            }

            $destDir = \dirname($destPath);
            if (!is_dir($destDir)) {
                $filesystem->mkdir($destDir);
            }

            $filesystem->copy($file->getRealPath(), $destPath);
            $createdFiles[] = $relativePath;
        }

        sort($createdFiles);
        $io->success(\sprintf('Site scaffolded in "%s"', $path));
        $io->listing($createdFiles);

        return Command::SUCCESS;
    }

    private function getScaffoldDir(): string
    {
        // resources/scaffold/ relative to the project root
        $dir = \dirname(__DIR__, 2).'/resources/scaffold';
        if (!is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Scaffold directory not found at "%s".', $dir));
        }

        return $dir;
    }
}
