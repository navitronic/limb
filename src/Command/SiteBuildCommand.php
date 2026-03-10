<?php

declare(strict_types=1);

namespace App\Command;

use App\Config\ConfigLoader;
use App\Config\ConfigMerger;
use App\Content\ContentClassification;
use App\Content\ContentLocator;
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
        private readonly ConfigLoader $configLoader,
        private readonly ConfigMerger $configMerger,
        private readonly ContentLocator $contentLocator,
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

        $yamlConfig = $this->configLoader->load($source, $configPath);

        $cliOverrides = [];
        $destination = $input->getOption('destination');
        if (\is_string($destination)) {
            $cliOverrides['destination'] = $destination;
        }

        $config = $this->configMerger->merge($yamlConfig, $cliOverrides, $source);

        if ($output->isVerbose()) {
            $io->section('Configuration');
            $io->listing([
                \sprintf('title = "%s"', $config->title),
                \sprintf('baseUrl = "%s"', $config->baseUrl),
                \sprintf('source = "%s"', $config->source),
                \sprintf('destination = "%s"', $config->destination),
                \sprintf('permalink = "%s"', $config->permalink),
                \sprintf('timezone = "%s"', $config->timezone),
                \sprintf('layoutsDir = "%s"', $config->layoutsDir),
                \sprintf('includesDir = "%s"', $config->includesDir),
                \sprintf('dataDir = "%s"', $config->dataDir),
                \sprintf('postsDir = "%s"', $config->postsDir),
            ]);
        }

        // Content discovery
        $scanResult = $this->contentLocator->scan($config);

        if ($output->isVerbose()) {
            $io->section('Content Discovery');
            $io->listing([
                \sprintf('Found %d pages', $scanResult->countByClassification(ContentClassification::Page)),
                \sprintf('Found %d posts', $scanResult->countByClassification(ContentClassification::Post)),
                \sprintf('Found %d layouts', $scanResult->countByClassification(ContentClassification::Layout)),
                \sprintf('Found %d includes', $scanResult->countByClassification(ContentClassification::Include)),
                \sprintf('Found %d static files', $scanResult->countByClassification(ContentClassification::Static)),
            ]);
        }

        return Command::SUCCESS;
    }
}
