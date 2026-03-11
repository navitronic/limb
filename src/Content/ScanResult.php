<?php

declare(strict_types=1);

namespace Limb\Content;

final class ScanResult
{
    /** @var array<string, list<string>> */
    private array $files = [];

    /** @var array<string, list<string>> collection name => file paths */
    private array $collectionFiles = [];

    public function add(ContentClassification $classification, string $path): void
    {
        $this->files[$classification->value][] = $path;
    }

    public function addCollectionFile(string $collectionName, string $path): void
    {
        $this->collectionFiles[$collectionName][] = $path;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCollectionFiles(): array
    {
        return $this->collectionFiles;
    }

    /**
     * @return list<string>
     */
    public function getByClassification(ContentClassification $classification): array
    {
        return $this->files[$classification->value] ?? [];
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->files as $paths) {
            $all = [...$all, ...$paths];
        }

        return $all;
    }

    public function countByClassification(ContentClassification $classification): int
    {
        return \count($this->getByClassification($classification));
    }
}
