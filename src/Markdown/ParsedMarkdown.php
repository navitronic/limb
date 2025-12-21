<?php

namespace Limb\Markdown;

final class ParsedMarkdown
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $content,
        public readonly array $metadata,
    ) {}
}
