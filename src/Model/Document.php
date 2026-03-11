<?php

declare(strict_types=1);

namespace Limb\Model;

final class Document
{
    /**
     * @param array<string, mixed> $frontMatter
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $relativePath,
        public readonly array $frontMatter,
        public readonly string $rawContent,
        public readonly string $contentType,
        public readonly string $title,
        public readonly string $slug,
        public readonly bool $published,
        public readonly ?string $layoutName = null,
        public readonly ?string $collection = null,
        public readonly ?\DateTimeInterface $date = null,
        public string $outputPath = '',
        public string $url = '',
        public ?string $renderedContent = null,
    ) {
    }
}
