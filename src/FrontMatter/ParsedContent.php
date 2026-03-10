<?php

declare(strict_types=1);

namespace App\FrontMatter;

final readonly class ParsedContent
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $metadata,
        public string $body,
        public bool $hasFrontMatter,
    ) {
    }
}
