<?php

declare(strict_types=1);

namespace App\Command;

use App\Collection\CollectionBuilder;
use App\Config\ConfigLoader;
use App\Config\ConfigMerger;
use App\Content\ContentClassification;
use App\Content\ContentLocator;
use App\Data\DataLoader;
use App\FrontMatter\FrontMatterParser;
use App\Model\Document;
use App\Model\DocumentFactory;
use App\Model\Site;
use App\Permalink\OutputPathResolver;
use App\Permalink\PermalinkGenerator;
use App\Rendering\DocumentRenderer;
use App\Rendering\TwigEnvironmentFactory;
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
        private readonly FrontMatterParser $frontMatterParser,
        private readonly DocumentFactory $documentFactory,
        private readonly DataLoader $dataLoader,
        private readonly PermalinkGenerator $permalinkGenerator,
        private readonly OutputPathResolver $outputPathResolver,
        private readonly CollectionBuilder $collectionBuilder,
        private readonly TwigEnvironmentFactory $twigEnvironmentFactory,
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

        // Parse front matter and create documents
        $pages = $this->createDocuments(
            $scanResult->getByClassification(ContentClassification::Page),
            'page',
        );
        $posts = $this->createDocuments(
            $scanResult->getByClassification(ContentClassification::Post),
            'post',
        );

        $includeDrafts = (bool) $input->getOption('drafts');
        if ($includeDrafts) {
            $drafts = $this->createDocuments(
                $scanResult->getByClassification(ContentClassification::Draft),
                'draft',
            );
            $posts = array_merge($posts, $drafts);
        }

        // Load data files
        $dataDir = $config->source.'/'.$config->dataDir;
        $data = $this->dataLoader->load($dataDir);

        // Generate permalinks and output paths
        $allDocuments = array_merge($pages, $posts);
        foreach ($allDocuments as $doc) {
            $pattern = $this->resolvePermalinkPattern($doc, $config->permalink);
            $doc->url = $this->permalinkGenerator->generate($doc, $pattern);
            $doc->outputPath = $this->outputPathResolver->resolve($doc->url, $config->destination);
        }

        // Build collections
        $collections = $this->collectionBuilder->build($allDocuments, $config);

        // Assemble site model
        $site = new Site(
            config: $config,
            pages: $pages,
            posts: $posts,
            collections: $collections,
            data: $data,
            staticAssets: $scanResult->getByClassification(ContentClassification::Static),
        );

        // Render documents
        $twig = $this->twigEnvironmentFactory->create($config->source);
        $markdownRenderer = new \App\Markdown\MarkdownRenderer();
        $renderer = new DocumentRenderer($twig, $markdownRenderer);

        $rendered = 0;
        foreach ($allDocuments as $doc) {
            $doc->renderedContent = $renderer->render($doc, $site);
            ++$rendered;
        }

        if ($output->isVerbose()) {
            $io->section('Rendering');
            $io->listing([
                \sprintf('Rendered %d documents', $rendered),
            ]);
        }

        $io->success(\sprintf('Build complete: %d documents rendered.', $rendered));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $paths
     *
     * @return Document[]
     */
    private function createDocuments(array $paths, string $type): array
    {
        $documents = [];

        foreach ($paths as $absolutePath) {
            $content = file_get_contents($absolutePath);
            if (false === $content) {
                continue;
            }

            $relativePath = basename(\dirname($absolutePath)).'/'.basename($absolutePath);
            $parsed = $this->frontMatterParser->parse($content, $absolutePath);

            $documents[] = match ($type) {
                'post' => $this->documentFactory->createPost($absolutePath, $relativePath, $parsed),
                'draft' => $this->documentFactory->createDraft($absolutePath, $relativePath, $parsed),
                default => $this->documentFactory->createPage($absolutePath, $relativePath, $parsed),
            };
        }

        return $documents;
    }

    private function resolvePermalinkPattern(Document $doc, string $defaultPattern): string
    {
        // Posts use the configured permalink pattern, pages use their relative path
        if ('posts' === $doc->collection) {
            return $defaultPattern;
        }

        // Pages: use filename-based URL (e.g. about.md → /about/)
        $slug = $doc->slug;

        if ('index' === $slug) {
            return '/';
        }

        return '/'.$slug.'/';
    }
}
