<?php

declare(strict_types=1);

namespace App\Data;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class DataLoader
{
    /**
     * Load all data files from the given directory into a nested array.
     *
     * Files are keyed by filename (without extension).
     * Subdirectories create nested keys: _data/authors/team.yml → data['authors']['team'].
     *
     * @return array<string, mixed>
     */
    public function load(string $dataDir): array
    {
        if (!is_dir($dataDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($dataDir)
            ->name(['*.yml', '*.yaml', '*.json'])
            ->sortByName();

        $data = [];

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $parsed = $this->parseFile($file->getRealPath(), $relativePath);
            $this->setNestedValue($data, $relativePath, $parsed);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function parseFile(string $absolutePath, string $relativePath): array
    {
        $extension = strtolower(pathinfo($absolutePath, \PATHINFO_EXTENSION));

        if ('json' === $extension) {
            return $this->parseJsonFile($absolutePath, $relativePath);
        }

        return $this->parseYamlFile($absolutePath, $relativePath);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function parseYamlFile(string $absolutePath, string $relativePath): array
    {
        try {
            $parsed = Yaml::parseFile($absolutePath);
        } catch (ParseException $e) {
            throw new \RuntimeException(\sprintf('Failed to parse data file "%s": %s', $relativePath, $e->getMessage()), 0, $e);
        }

        if (!\is_array($parsed)) {
            return [];
        }

        /** @var array<string, mixed>|list<mixed> $result */
        $result = $parsed;

        return $result;
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function parseJsonFile(string $absolutePath, string $relativePath): array
    {
        $contents = file_get_contents($absolutePath);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Failed to read data file "%s"', $relativePath));
        }

        try {
            $parsed = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(\sprintf('Failed to parse data file "%s": %s', $relativePath, $e->getMessage()), 0, $e);
        }

        if (!\is_array($parsed)) {
            return [];
        }

        /** @var array<string, mixed>|list<mixed> $result */
        $result = $parsed;

        return $result;
    }

    /**
     * Set a value in a nested array based on the relative file path.
     *
     * Example: "authors/team.yml" → $data['authors']['team'] = $value
     *
     * @param array<string, mixed>             $data
     * @param array<string, mixed>|list<mixed> $value
     */
    private function setNestedValue(array &$data, string $relativePath, array $value): void
    {
        $parts = explode('/', $relativePath);
        $filename = array_pop($parts);
        $key = pathinfo($filename, \PATHINFO_FILENAME);

        $current = &$data;

        foreach ($parts as $part) {
            if (!isset($current[$part]) || !\is_array($current[$part])) {
                $current[$part] = [];
            }

            /** @var array<string, mixed> $current */
            $current = &$current[$part];
        }

        $current[$key] = $value;
    }
}
