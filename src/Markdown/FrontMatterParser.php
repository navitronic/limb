<?php

namespace Limb\Markdown;

use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterParserInterface;

final class FrontMatterParser
{
    private FrontMatterParserInterface $parser;

    public function __construct(?FrontMatterParserInterface $parser = null)
    {
        $this->parser = $parser ?? new FrontMatterExtension()->getFrontMatterParser();
    }

    /**
     * @throws InvalidFrontMatterException
     */
    public function parse(string $markdown): ParsedMarkdown
    {
        $parsed = $this->parser->parse($markdown);

        return new ParsedMarkdown($parsed->getContent(), $this->normalizeMetadata($parsed->getFrontMatter()));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $frontMatter): array
    {
        if (!\is_array($frontMatter)) {
            return [];
        }

        $metadata = [];
        foreach (\array_keys($frontMatter) as $key) {
            if (!\is_string($key)) {
                continue;
            }

            $metadata[$key] = $frontMatter[$key];
        }

        return $metadata;
    }
}
