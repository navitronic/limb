<?php

declare(strict_types=1);

namespace Limb\Pipeline;

use Limb\Asset\AssetCopier;
use Limb\Collection\CollectionBuilder;
use Limb\Config\ConfigLoader;
use Limb\Config\ConfigMerger;
use Limb\Config\SiteConfig;
use Limb\Content\ContentClassification;
use Limb\Content\ContentLocator;
use Limb\Data\DataLoader;
use Limb\Event\AfterRenderEvent;
use Limb\Event\BeforeRenderEvent;
use Limb\Event\BuildCompleteEvent;
use Limb\Event\SiteLoadedEvent;
use Limb\Exception\RenderException;
use Limb\FrontMatter\FrontMatterParser;
use Limb\Markdown\MarkdownRenderer;
use Limb\Model\BuildResult;
use Limb\Model\Document;
use Limb\Model\DocumentFactory;
use Limb\Model\Site;
use Limb\Output\OutputWriter;
use Limb\Permalink\OutputPathResolver;
use Limb\Permalink\PermalinkGenerator;
use Limb\Rendering\DocumentRenderer;
use Limb\Rendering\TwigEnvironmentFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class BuildRunner
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
        private readonly ConfigMerger $configMerger,
        private readonly ContentLocator $contentLocator,
        private readonly FrontMatterParser $frontMatterParser,
        private readonly DocumentFactory $documentFactory,
        private readonly DataLoader $dataLoader,
        private readonly CollectionBuilder $collectionBuilder,
        private readonly PermalinkGenerator $permalinkGenerator,
        private readonly OutputPathResolver $outputPathResolver,
        private readonly TwigEnvironmentFactory $twigEnvironmentFactory,
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly OutputWriter $outputWriter,
        private readonly AssetCopier $assetCopier,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Execute the full build pipeline.
     *
     * @param array<string, mixed> $cliOverrides additional config overrides from CLI options
     */
    public function build(
        string $sourceDir,
        ?string $destinationDir = null,
        ?string $configPath = null,
        bool $includeDrafts = false,
        array $cliOverrides = [],
    ): BuildResult {
        $startTime = microtime(true);
        $errors = [];

        // 1. Load configuration
        $yamlConfig = $this->configLoader->load($sourceDir, $configPath);
        if (null !== $destinationDir) {
            $cliOverrides['destination'] = $destinationDir;
        }
        $config = $this->configMerger->merge($yamlConfig, $cliOverrides, $sourceDir);

        // 2. Scan site files
        $scanResult = $this->contentLocator->scan($config);

        // 3-5. Parse front matter and create Document models
        $pages = $this->createDocuments(
            $scanResult->getByClassification(ContentClassification::Page),
            'page',
        );
        $posts = $this->createDocuments(
            $scanResult->getByClassification(ContentClassification::Post),
            'post',
        );

        if ($includeDrafts) {
            $drafts = $this->createDocuments(
                $scanResult->getByClassification(ContentClassification::Draft),
                'draft',
            );
            $posts = array_merge($posts, $drafts);
        }

        // 4. Load data files
        $data = $this->dataLoader->load($config->source.'/'.$config->dataDir);

        // 5. Create collection documents
        $collectionDocuments = $this->createCollectionDocuments($scanResult->getCollectionFiles(), $config);

        // 6. Build collections
        $allDocuments = array_merge($pages, $posts, $collectionDocuments);
        $collections = $this->collectionBuilder->build($allDocuments, $config);

        // 7. Compute URLs and output paths
        foreach ($allDocuments as $doc) {
            $pattern = $this->resolvePermalinkPattern($doc, $config);
            $doc->url = $this->permalinkGenerator->generate($doc, $pattern);
            $doc->outputPath = $this->outputPathResolver->resolve($doc->url, $config->destination);
        }

        // 8. Render all documents
        $site = new Site(
            config: $config,
            pages: $pages,
            posts: $posts,
            collections: $collections,
            data: $data,
            staticAssets: $scanResult->getByClassification(ContentClassification::Static),
        );

        $this->eventDispatcher->dispatch(new SiteLoadedEvent($site));

        $twig = $this->twigEnvironmentFactory->create($config->source, $config->baseUrl, $config->url);
        $renderer = new DocumentRenderer($twig, $this->markdownRenderer);

        $this->eventDispatcher->dispatch(new BeforeRenderEvent($allDocuments, $site));

        foreach ($allDocuments as $doc) {
            try {
                $doc->renderedContent = $renderer->render($doc, $site);
            } catch (RenderException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->eventDispatcher->dispatch(new AfterRenderEvent($allDocuments, $site));

        // 9. Write rendered output
        $filesWritten = $this->outputWriter->write($allDocuments);

        // 10. Copy static assets
        $staticFilesCopied = $this->assetCopier->copy(
            $site->staticAssets,
            $config->source,
            $config->destination,
        );

        $elapsedTime = microtime(true) - $startTime;

        $result = new BuildResult(
            pagesRendered: \count($pages),
            postsRendered: \count($posts),
            staticFilesCopied: $staticFilesCopied,
            errors: $errors,
            elapsedTime: $elapsedTime,
        );

        $this->eventDispatcher->dispatch(new BuildCompleteEvent($result));

        return $result;
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

    private function resolvePermalinkPattern(Document $doc, SiteConfig $config): string
    {
        if ('posts' === $doc->collection) {
            return $config->permalink;
        }

        // Custom collection with configured permalink
        if (null !== $doc->collection && isset($config->collections[$doc->collection])) {
            $collectionConfig = $config->collections[$doc->collection];
            if (\is_array($collectionConfig) && isset($collectionConfig['permalink']) && \is_string($collectionConfig['permalink'])) {
                return $collectionConfig['permalink'];
            }
        }

        if ('index' === $doc->slug) {
            return '/';
        }

        return '/'.$doc->slug.'/';
    }

    /**
     * @param array<string, list<string>> $collectionFiles
     *
     * @return Document[]
     */
    private function createCollectionDocuments(array $collectionFiles, SiteConfig $config): array
    {
        $documents = [];

        foreach ($collectionFiles as $collectionName => $paths) {
            $collectionConfig = $config->collections[$collectionName] ?? [];
            $output = true;
            if (\is_array($collectionConfig) && isset($collectionConfig['output']) && \is_bool($collectionConfig['output'])) {
                $output = $collectionConfig['output'];
            }

            if (!$output) {
                continue;
            }

            foreach ($paths as $absolutePath) {
                $content = file_get_contents($absolutePath);
                if (false === $content) {
                    continue;
                }

                $relativePath = basename(\dirname($absolutePath)).'/'.basename($absolutePath);
                $parsed = $this->frontMatterParser->parse($content, $absolutePath);
                $documents[] = $this->documentFactory->createCollectionDocument($absolutePath, $relativePath, $parsed, $collectionName);
            }
        }

        return $documents;
    }
}
