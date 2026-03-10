<?php

declare(strict_types=1);

namespace App\Command;

use App\Config\ConfigLoader;
use App\Config\ConfigMerger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'site:clean',
    description: 'Delete the destination directory',
)]
class SiteCleanCommand extends Command
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
        private readonly ConfigMerger $configMerger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source directory', '/site')
            ->addOption('destination', 'd', InputOption::VALUE_REQUIRED, 'Destination directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $source = $input->getOption('source');
        \assert(\is_string($source));

        $yamlConfig = $this->configLoader->load($source);

        $cliOverrides = [];
        $destination = $input->getOption('destination');
        if (\is_string($destination)) {
            $cliOverrides['destination'] = $destination;
        }

        $config = $this->configMerger->merge($yamlConfig, $cliOverrides, $source);
        $destDir = $config->destination;

        if (!is_dir($destDir)) {
            $io->text(\sprintf('Nothing to clean: "%s" does not exist.', $destDir));

            return Command::SUCCESS;
        }

        $filesystem = new Filesystem();
        $filesystem->remove($destDir);

        $io->success(\sprintf('Cleaned: "%s" deleted.', $destDir));

        return Command::SUCCESS;
    }
}
