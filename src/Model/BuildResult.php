<?php

declare(strict_types=1);

namespace Limb\Model;

final class BuildResult
{
    /**
     * @param string[] $errors
     * @param string[] $warnings
     */
    public function __construct(
        public int $pagesRendered = 0,
        public int $postsRendered = 0,
        public int $staticFilesCopied = 0,
        public array $errors = [],
        public array $warnings = [],
        public float $elapsedTime = 0.0,
    ) {
    }
}
