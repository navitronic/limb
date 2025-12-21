<?php

declare(strict_types=1);

namespace Limb\Console\Command;

use Limb\Markdown\MarkdownRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RenderCommand extends Command
{
    protected static $defaultName = 'render';
    protected static $defaultDescription = 'Render a Markdown file to HTML.';

    /**
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    public function __construct(
        private readonly ?MarkdownRenderer $renderer = null,
    ) {
        parent::__construct();
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the markdown file.');
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $renderer = $this->renderer ?? new MarkdownRenderer();

        try {
            $limb = $renderer->parseFile($path);
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
