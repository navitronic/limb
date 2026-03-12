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
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'site:serve',
    description: 'Build the site and start a local development server',
)]
class SiteServeCommand extends Command
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
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to bind to', '0.0.0.0')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port to serve on', '4000')
            ->addOption('destination', 'd', InputOption::VALUE_REQUIRED, 'Destination directory')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('drafts', null, InputOption::VALUE_NONE, 'Include draft posts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $source = $input->getOption('source');
        \assert(\is_string($source));

        $host = $input->getOption('host');
        \assert(\is_string($host));

        $port = $input->getOption('port');
        \assert(\is_string($port));

        $configPath = $input->getOption('config');
        \assert(null === $configPath || \is_string($configPath));

        $destination = $input->getOption('destination');
        \assert(null === $destination || \is_string($destination));

        $includeDrafts = (bool) $input->getOption('drafts');

        // 1. Build the site
        $io->text('Building site...');
        $result = $this->buildRunner->build(
            sourceDir: $source,
            destinationDir: $destination,
            configPath: $configPath,
            includeDrafts: $includeDrafts,
        );

        if ([] !== $result->errors) {
            foreach ($result->errors as $error) {
                $io->error($error);
            }

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Build complete: %d pages, %d posts, %d static files in %.2fs.',
            $result->pagesRendered,
            $result->postsRendered,
            $result->staticFilesCopied,
            $result->elapsedTime,
        ));

        // Determine the destination directory from the build result
        $destDir = $result->destinationDir;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0o777, true);
        }

        // 2. Start PHP built-in server
        $addr = $host.':'.$port;
        $io->text(\sprintf('Serving at <info>http://%s</info> — press Ctrl+C to stop.', $addr));

        $process = new Process(['php', '-S', $addr, '-t', $destDir]);
        $process->setTimeout(null);

        $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        return Command::SUCCESS;
    }
}
