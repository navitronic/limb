<?php

declare(strict_types=1);

namespace Limb\Content;

use Limb\Config\SiteConfig;
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
        $collectionNames = $this->getCollectionNames($config);

        $finder = new Finder();
        $finder->files()
            ->in($sourceDir)
            ->ignoreDotFiles(true)
            ->notName('_config.yml');

        // Exclude destination directory from scanning
        $destExclude = $config->destination;
        $sourcePrefix = rtrim($sourceDir, '/').'/';
        if (str_starts_with($destExclude, $sourcePrefix)) {
            $destExclude = substr($destExclude, \strlen($sourcePrefix));
        }
        $finder->exclude($destExclude);

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

            // Check if file belongs to a custom collection
            $collectionName = $this->matchCollection($relativePath, $collectionNames);
            if (null !== $collectionName) {
                $extension = strtolower(pathinfo($relativePath, \PATHINFO_EXTENSION));
                if (\in_array($extension, self::CONTENT_EXTENSIONS, true)) {
                    $result->addCollectionFile($collectionName, $fullPath);

                    continue;
                }
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

    /**
     * Get collection directory names from config.
     *
     * @return list<string>
     */
    private function getCollectionNames(SiteConfig $config): array
    {
        return array_keys($config->collections);
    }

    /**
     * Check if a file path belongs to a custom collection directory (_<name>/).
     *
     * @param list<string> $collectionNames
     */
    private function matchCollection(string $relativePath, array $collectionNames): ?string
    {
        $normalizedPath = str_replace('\\', '/', $relativePath);

        foreach ($collectionNames as $name) {
            if (str_starts_with($normalizedPath, '_'.$name.'/')) {
                return $name;
            }
        }

        return null;
    }
}
