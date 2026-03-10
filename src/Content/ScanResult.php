<?php

declare(strict_types=1);

namespace App\Content;

final class ScanResult
{
    /** @var array<string, list<string>> */
    private array $files = [];

    public function add(ContentClassification $classification, string $path): void
    {
        $this->files[$classification->value][] = $path;
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
