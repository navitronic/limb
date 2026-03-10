<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\SiteConfig;

final class Site
{
    /**
     * @param Document[]                $pages
     * @param Document[]                $posts
     * @param array<string, Collection> $collections
     * @param array<string, mixed>      $data
     * @param list<string>              $staticAssets
     */
    public function __construct(
        public readonly SiteConfig $config,
        public array $pages = [],
        public array $posts = [],
        public array $collections = [],
        public array $data = [],
        public array $staticAssets = [],
    ) {
    }
}
