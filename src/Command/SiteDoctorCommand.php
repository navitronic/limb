<?php

declare(strict_types=1);

namespace Limb\Command;

use Limb\Config\ConfigLoader;
use Limb\Config\ConfigMerger;
use Limb\Content\ContentClassification;
use Limb\Content\ContentLocator;
use Limb\FrontMatter\FrontMatterParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'site:doctor',
    description: 'Validate site configuration and structure',
)]
class SiteDoctorCommand extends Command
{
    private const array KNOWN_PERMALINK_TOKENS = [
        'year', 'month', 'day', 'title', 'slug', 'categories', 'category',
    ];

    public function __construct(
        private readonly ConfigLoader $configLoader,
        private readonly ConfigMerger $configMerger,
        private readonly ContentLocator $contentLocator,
        private readonly FrontMatterParser $frontMatterParser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source directory', '/site');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $source = $input->getOption('source');
        \assert(\is_string($source));

        /** @var list<string> $errors */
        $errors = [];
        /** @var list<string> $warnings */
        $warnings = [];

        // 1. Check _config.yml exists and is valid
        $configFile = rtrim($source, '/').'/_config.yml';
        if (!is_file($configFile)) {
            $errors[] = '_config.yml not found in source directory.';
            $this->reportResults($io, $errors, $warnings);

            return Command::FAILURE;
        }

        try {
            $yamlConfig = $this->configLoader->load($source);
            $config = $this->configMerger->merge($yamlConfig, [], $source);
        } catch (\Throwable $e) {
            $errors[] = 'Invalid _config.yml: '.$e->getMessage();
            $this->reportResults($io, $errors, $warnings);

            return Command::FAILURE;
        }

        $io->text('✓ _config.yml is valid');

        // 2. Check permalink tokens
        $this->checkPermalinkTokens($config->permalink, $warnings);

        // 3. Scan files
        $scanResult = $this->contentLocator->scan($config);

        // 4. Collect available layouts
        $layoutDir = rtrim($source, '/').'/'.$config->layoutsDir;
        $availableLayouts = [];
        if (is_dir($layoutDir)) {
            foreach (glob($layoutDir.'/*.html.twig') ?: [] as $layoutPath) {
                $name = pathinfo($layoutPath, \PATHINFO_FILENAME);
                // Remove .html from the name (file is name.html.twig)
                $availableLayouts[] = str_replace('.html', '', $name);
            }
        }

        // 5. Collect available includes
        $includesDir = rtrim($source, '/').'/'.$config->includesDir;
        $availableIncludes = [];
        if (is_dir($includesDir)) {
            foreach (glob($includesDir.'/*.html.twig') ?: [] as $includePath) {
                $availableIncludes[] = basename($includePath);
            }
        }

        // 6. Check layouts referenced by documents
        $contentFiles = array_merge(
            $scanResult->getByClassification(ContentClassification::Page),
            $scanResult->getByClassification(ContentClassification::Post),
            $scanResult->getByClassification(ContentClassification::Draft),
        );

        foreach ($contentFiles as $filePath) {
            $content = file_get_contents($filePath);
            if (false === $content) {
                continue;
            }

            $parsed = $this->frontMatterParser->parse($content, $filePath);
            $layout = $parsed->metadata['layout'] ?? null;

            if (\is_string($layout) && !\in_array($layout, $availableLayouts, true)) {
                $errors[] = \sprintf(
                    'Layout "%s" referenced by "%s" not found in %s.',
                    $layout,
                    basename($filePath),
                    $config->layoutsDir,
                );
            }
        }

        // 7. Check includes referenced by layouts
        $layoutFiles = $scanResult->getByClassification(ContentClassification::Layout);
        foreach ($layoutFiles as $layoutPath) {
            $content = file_get_contents($layoutPath);
            if (false === $content) {
                continue;
            }

            if (preg_match_all('/{%\s*include\s+[\'"]@includes\/([^\'"]+)[\'"]\s*%}/', $content, $matches)) {
                foreach ($matches[1] as $includeName) {
                    if (!\in_array($includeName, $availableIncludes, true)) {
                        $warnings[] = \sprintf(
                            'Include "%s" referenced in "%s" not found in %s.',
                            $includeName,
                            basename($layoutPath),
                            $config->includesDir,
                        );
                    }
                }
            }
        }

        // 8. Check post filenames
        $postFiles = $scanResult->getByClassification(ContentClassification::Post);
        foreach ($postFiles as $postPath) {
            $filename = basename($postPath);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}-.+\.md$/', $filename)) {
                $warnings[] = \sprintf(
                    'Post filename "%s" does not follow YYYY-MM-DD-slug.md convention.',
                    $filename,
                );
            }
        }

        $this->reportResults($io, $errors, $warnings);

        return [] === $errors ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<string> $warnings
     */
    private function checkPermalinkTokens(string $permalink, array &$warnings): void
    {
        if (preg_match_all('/:([a-z_]+)/', $permalink, $matches)) {
            foreach ($matches[1] as $token) {
                if (!\in_array($token, self::KNOWN_PERMALINK_TOKENS, true)) {
                    $warnings[] = \sprintf(
                        'Unknown permalink token ":%s" in permalink pattern "%s".',
                        $token,
                        $permalink,
                    );
                }
            }
        }
    }

    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    private function reportResults(SymfonyStyle $io, array $errors, array $warnings): void
    {
        foreach ($warnings as $warning) {
            $io->warning($warning);
        }

        foreach ($errors as $error) {
            $io->error($error);
        }

        if ([] === $errors && [] === $warnings) {
            $io->success('All checks passed. Site looks healthy!');
        } elseif ([] === $errors) {
            $io->text('OK with warnings.');
        }
    }
}
