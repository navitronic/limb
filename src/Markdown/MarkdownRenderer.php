<?php

declare(strict_types=1);

namespace Limb\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use Limb\Limb;

final class MarkdownRenderer
{
    private FrontMatterParser $frontMatterParser;
    private MarkdownHtmlRenderer $htmlRenderer;
    private MetadataResolver $metadataResolver;

    public function __construct(
        FrontMatterParser $frontMatterParser,
        MarkdownHtmlRenderer $htmlRenderer,
        MetadataResolver $metadataResolver,
    ) {
        $this->frontMatterParser = $frontMatterParser;
        $this->htmlRenderer = $htmlRenderer;
        $this->metadataResolver = $metadataResolver;
    }

    /**
     * @throws CommonMarkException
     */
    public function toHtml(string $markdown): string
    {
        return $this->htmlRenderer->toHtml($markdown);
    }

    /**
     * @return Limb|null Returns null when markdown cannot be parsed.
     */
    public function parse(string $markdown, ?string $sourcePath = null): ?Limb
    {
        try {
            $parsed = $this->frontMatterParser->parse($markdown);
            $resolved = $this->metadataResolver->resolve($parsed->metadata, $parsed->content, $sourcePath);
            $html = $this->htmlRenderer->toHtml($parsed->content);

            return new Limb(
                $resolved->title,
                $resolved->slug,
                $resolved->createdAt,
                $resolved->updatedAt,
                $parsed->content,
                $html,
                $parsed->metadata,
            );
        } catch (CommonMarkException|InvalidFrontMatterException) {
            return null;
        }
    }

    /**
     * @return Limb|null Returns null when markdown cannot be parsed.
     *
     * @throws \RuntimeException
     */
    public function parseFile(string $path): ?Limb
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw new \RuntimeException("Unable to read markdown file: {$path}");
        }

        $contents = \file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read markdown file: {$path}");
        }

        return $this->parse($contents, $path);
    }
}
