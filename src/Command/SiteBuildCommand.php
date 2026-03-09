<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'site:build',
    description: 'Build the static site',
)]
class SiteBuildCommand extends Command
{
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
        $output->writeln('Limb build starting...');

        return Command::SUCCESS;
    }
}
