<?php

declare(strict_types=1);

namespace Limb\Command;

use Limb\Pipeline\BuildRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'site:build',
    description: 'Build the static site',
)]
class SiteBuildCommand extends Command
{
    public function __construct(
        private readonly BuildRunner $buildRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source directory', '/site')
            ->addOption('destination', 'd', InputOption::VALUE_REQUIRED, 'Destination directory')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('drafts', null, InputOption::VALUE_NONE, 'Include draft posts')
            ->addOption('future', null, InputOption::VALUE_NONE, 'Include future-dated posts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->text('Limb build starting...');

        $source = $input->getOption('source');
        \assert(\is_string($source));

        $configPath = $input->getOption('config');
        \assert(null === $configPath || \is_string($configPath));

        $destination = $input->getOption('destination');
        \assert(null === $destination || \is_string($destination));

        $includeDrafts = (bool) $input->getOption('drafts');

        try {
            $result = $this->buildRunner->build(
                sourceDir: $source,
                destinationDir: $destination,
                configPath: $configPath,
                includeDrafts: $includeDrafts,
            );
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            if ($output->isVeryVerbose()) {
                $io->text((string) $e);
            }

            return Command::FAILURE;
        }

        if ([] !== $result->errors) {
            foreach ($result->errors as $error) {
                $io->error($error);
            }

            return Command::FAILURE;
        }

        if ([] !== $result->warnings) {
            foreach ($result->warnings as $warning) {
                $io->warning($warning);
            }
        }

        if ($output->isVerbose()) {
            $io->listing([
                \sprintf('Pages rendered: %d', $result->pagesRendered),
                \sprintf('Posts rendered: %d', $result->postsRendered),
                \sprintf('Static files copied: %d', $result->staticFilesCopied),
            ]);
        }

        $io->success(\sprintf(
            'Build complete: %d pages, %d posts, %d static files in %.2fs.',
            $result->pagesRendered,
            $result->postsRendered,
            $result->staticFilesCopied,
            $result->elapsedTime,
        ));

        return Command::SUCCESS;
    }
}
