<?php

declare(strict_types=1);

namespace App\Content;

use App\Config\SiteConfig;
use Symfony\Component\Finder\Finder;

final class ContentLocator
{
    private const array CONTENT_EXTENSIONS = ['md', 'markdown', 'html'];
    private const array DATA_EXTENSIONS = ['yml', 'yaml', 'json'];
    private const array DEFAULT_EXCLUDES = ['_site', '_config.yml'];

    public function scan(SiteConfig $config): ScanResult
    {
        $result = new ScanResult();
        $sourceDir = $config->source;

        $finder = new Finder();
        $finder->files()
            ->in($sourceDir)
            ->ignoreDotFiles(true)
            ->exclude($config->destination)
            ->notName('_config.yml');

        // Exclude _site and user-configured excludes
        foreach (self::DEFAULT_EXCLUDES as $exclude) {
            if ('_config.yml' !== $exclude) {
                $finder->exclude($exclude);
            }
        }

        foreach ($config->exclude as $exclude) {
            $finder->exclude($exclude);
        }

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $fullPath = $file->getRealPath();

            if (false === $fullPath) {
                continue;
            }

            $classification = $this->classify($relativePath, $config);
            $result->add($classification, $fullPath);
        }

        return $result;
    }

    private function classify(string $relativePath, SiteConfig $config): ContentClassification
    {
        $normalizedPath = str_replace('\\', '/', $relativePath);
        $extension = strtolower(pathinfo($normalizedPath, \PATHINFO_EXTENSION));

        // Layouts
        if (str_starts_with($normalizedPath, $config->layoutsDir.'/')) {
            return ContentClassification::Layout;
        }

        // Includes
        if (str_starts_with($normalizedPath, $config->includesDir.'/')) {
            return ContentClassification::Include;
        }

        // Data files
        if (str_starts_with($normalizedPath, $config->dataDir.'/') && \in_array($extension, self::DATA_EXTENSIONS, true)) {
            return ContentClassification::Data;
        }

        // Posts
        if (str_starts_with($normalizedPath, $config->postsDir.'/') && \in_array($extension, self::CONTENT_EXTENSIONS, true)) {
            return ContentClassification::Post;
        }

        // Drafts
        if (str_starts_with($normalizedPath, '_drafts/') && \in_array($extension, self::CONTENT_EXTENSIONS, true)) {
            return ContentClassification::Draft;
        }

        // Pages — markdown/html files in root or _pages/
        if (\in_array($extension, self::CONTENT_EXTENSIONS, true)) {
            $dir = \dirname($normalizedPath);
            if ('.' === $dir || str_starts_with($normalizedPath, '_pages/')) {
                return ContentClassification::Page;
            }
        }

        // Everything else in non-underscore directories is static
        return ContentClassification::Static;
    }
}
