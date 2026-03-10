<?php

declare(strict_types=1);

namespace App\Model;

final class Collection
{
    /**
     * @param Document[] $documents
     */
    public function __construct(
        public readonly string $name,
        public array $documents = [],
        public readonly ?string $permalink = null,
        public readonly bool $output = true,
    ) {
    }
}
