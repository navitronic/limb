<?php

declare(strict_types=1);

namespace Limb\Console\Command;

use Limb\Markdown\MarkdownRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RenderCommand extends Command
{
    protected static string $defaultName = 'render';
    protected static string $defaultDescription = 'Render a Markdown file to HTML.';

    /**
     * @throws LogicException
     */
    public function __construct(
        private readonly MarkdownRenderer $renderer,
    ) {
        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the markdown file.');
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');

        try {
            $limb = $this->renderer->parseFile($path);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($limb === null) {
            $output->writeln('<error>Failed to parse markdown.</error>');

            return Command::FAILURE;
        }

        $output->write($limb->html);

        return Command::SUCCESS;
    }
}
