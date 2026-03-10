<?php

declare(strict_types=1);

namespace App\Permalink;

final class OutputPathResolver
{
    /**
     * Resolve a URL to an absolute filesystem output path.
     *
     * URLs ending in `/` become `<dest>/<url>/index.html`.
     * URLs with a file extension are used as-is.
     */
    public function resolve(string $url, string $destinationDir): string
    {
        $trimmedUrl = ltrim($url, '/');

        if (str_ends_with($url, '/')) {
            return $destinationDir.'/'.$trimmedUrl.'index.html';
        }

        return $destinationDir.'/'.$trimmedUrl;
    }
}
