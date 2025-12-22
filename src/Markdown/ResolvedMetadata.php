<?php

namespace Limb\Markdown;

final class ResolvedMetadata
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly \DateTime $createdAt,
        public readonly ?\DateTime $updatedAt,
        public readonly array $extra,
    ) {}
}
