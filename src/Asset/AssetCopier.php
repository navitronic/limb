<?php

declare(strict_types=1);

namespace Limb\Asset;

final class AssetCopier
{
    /**
     * Copy static asset files from source to destination, preserving directory structure.
     *
     * @param list<string> $absolutePaths absolute paths to source files
     * @param string       $sourceDir     the site source root directory
     * @param string       $destDir       the site destination root directory
     */
    public function copy(array $absolutePaths, string $sourceDir, string $destDir): int
    {
        $count = 0;
        $sourcePrefix = rtrim($sourceDir, '/').'/';

        foreach ($absolutePaths as $absolutePath) {
            if (!is_file($absolutePath)) {
                continue;
            }

            $relativePath = $absolutePath;
            if (str_starts_with($absolutePath, $sourcePrefix)) {
                $relativePath = substr($absolutePath, \strlen($sourcePrefix));
            }

            $destPath = rtrim($destDir, '/').'/'.$relativePath;
            $destFileDir = \dirname($destPath);

            if (!is_dir($destFileDir)) {
                mkdir($destFileDir, 0o777, true);
            }

            copy($absolutePath, $destPath);
            ++$count;
        }

        return $count;
    }
}
