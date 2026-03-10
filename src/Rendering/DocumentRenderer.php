<?php

declare(strict_types=1);

namespace App\Rendering;

use App\Exception\RenderException;
use App\FrontMatter\FrontMatterParser;
use App\Markdown\MarkdownRenderer;
use App\Model\Document;
use App\Model\Site;
use Twig\Environment;

final class DocumentRenderer
{
    private const int MAX_LAYOUT_DEPTH = 10;

    private readonly FrontMatterParser $frontMatterParser;

    public function __construct(
        private readonly Environment $twig,
        private readonly MarkdownRenderer $markdownRenderer,
    ) {
        $this->frontMatterParser = new FrontMatterParser();
    }

    /**
     * Render a document, optionally wrapping it in a Twig layout.
     *
     * If the document has a layout, the layout template is rendered with
     * `site`, `page`, and `content` variables available.
     * Layout chaining is supported: a layout can declare its own parent layout
     * via YAML front matter.
     */
    public function render(Document $doc, Site $site): string
    {
        $renderedBody = $this->renderBody($doc);

        if (null === $doc->layoutName) {
            return $renderedBody;
        }

        return $this->renderWithLayoutChain($doc, $site, $renderedBody);
    }

    private function renderBody(Document $doc): string
    {
        if ('md' === $doc->contentType) {
            return $this->markdownRenderer->render($doc->rawContent);
        }

        return $doc->rawContent;
    }

    private function renderWithLayoutChain(Document $doc, Site $site, string $renderedBody): string
    {
        $context = $this->buildContext($doc, $site, $renderedBody);
        $layoutName = $doc->layoutName;
        $depth = 0;

        while (null !== $layoutName) {
            if ($depth >= self::MAX_LAYOUT_DEPTH) {
                throw new RenderException(\sprintf(
                    'Layout chain exceeded maximum depth of %d for document "%s". Check for circular layout references.',
                    self::MAX_LAYOUT_DEPTH,
                    $doc->relativePath,
                ));
            }

            $templateRef = '@layouts/'.$layoutName.'.html.twig';
            $layoutSource = $this->loadLayoutSource($layoutName, $doc->relativePath);

            // Parse front matter from the layout to find parent layout
            $parsed = $this->frontMatterParser->parse($layoutSource, $templateRef);

            // Render the layout body (after front matter extraction) as a Twig template
            $template = $this->twig->createTemplate($parsed->body);
            $context['content'] = $template->render($context);

            // Check if this layout has a parent layout
            $layoutName = null;
            if (isset($parsed->metadata['layout']) && \is_string($parsed->metadata['layout'])) {
                $layoutName = $parsed->metadata['layout'];
            }

            ++$depth;
        }

        /** @var string $result */
        $result = $context['content'];

        return $result;
    }

    private function loadLayoutSource(string $layoutName, string $documentPath): string
    {
        $templateRef = '@layouts/'.$layoutName.'.html.twig';

        try {
            $source = $this->twig->getLoader()->getSourceContext($templateRef);
        } catch (\Exception $e) {
            throw new RenderException(\sprintf(
                'Layout "%s" not found for document "%s": %s',
                $layoutName,
                $documentPath,
                $e->getMessage(),
            ), 0, $e);
        }

        return $source->getCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Document $doc, Site $site, string $renderedBody): array
    {
        return [
            'site' => $this->buildSiteContext($site),
            'page' => $this->buildPageContext($doc),
            'content' => $renderedBody,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSiteContext(Site $site): array
    {
        return [
            'title' => $site->config->title,
            'baseUrl' => $site->config->baseUrl,
            'url' => $site->config->url,
            'posts' => $site->posts,
            'pages' => $site->pages,
            'collections' => $site->collections,
            'data' => $site->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPageContext(Document $doc): array
    {
        return array_merge($doc->frontMatter, [
            'url' => $doc->url,
            'date' => $doc->date,
            'title' => $doc->title,
            'slug' => $doc->slug,
            'collection' => $doc->collection,
            'content' => $doc->rawContent,
        ]);
    }
}
