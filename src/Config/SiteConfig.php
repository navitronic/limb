<?php

declare(strict_types=1);

namespace Limb\Config;

final readonly class SiteConfig
{
    /**
     * @param string[]             $exclude
     * @param string[]             $include
     * @param array<string, mixed> $collections
     * @param array<int, mixed>    $defaults
     */
    public function __construct(
        public string $title = '',
        public string $baseUrl = '',
        public string $url = '',
        public string $source = '/site',
        public string $destination = '_site',
        public string $layoutsDir = '_layouts',
        public string $includesDir = '_includes',
        public string $dataDir = '_data',
        public string $postsDir = '_posts',
        public string $permalink = '/:year/:month/:day/:title/',
        public string $timezone = 'UTC',
        public array $exclude = [],
        public array $include = [],
        public array $collections = [],
        public array $defaults = [],
    ) {
    }
}
