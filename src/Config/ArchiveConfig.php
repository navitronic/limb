<?php

declare(strict_types=1);

namespace Limb\Config;

final readonly class ArchiveConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $layout = 'archive',
    ) {
    }
}
