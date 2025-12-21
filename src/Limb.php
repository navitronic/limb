<?php

declare(strict_types=1);

namespace Limb;

class Limb
{
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly \DateTime $createdAt,
        public readonly ?\DateTime $updatedAt,
        public readonly string $content,
        public readonly string $html,
        public readonly array $metadata,
    ) {}

    public function updatedAt()
    {
        return $this->updatedAt ?? $this->createdAt;
    }
}
