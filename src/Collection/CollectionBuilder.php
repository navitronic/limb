<?php

declare(strict_types=1);

namespace App\Collection;

use App\Config\SiteConfig;
use App\Model\Collection;
use App\Model\Document;

final class CollectionBuilder
{
    /**
     * Group documents by collection, sort posts by date (newest first),
     * and apply collection config (output, permalink).
     *
     * @param Document[] $documents
     *
     * @return array<string, Collection>
     */
    public function build(array $documents, SiteConfig $config): array
    {
        $grouped = $this->groupByCollection($documents);

        $collections = [];
        foreach ($grouped as $name => $docs) {
            $this->sortByDateDescending($docs);

            $collectionConfig = $config->collections[$name] ?? [];
            $output = true;
            $permalink = null;

            if (\is_array($collectionConfig)) {
                if (isset($collectionConfig['output']) && \is_bool($collectionConfig['output'])) {
                    $output = $collectionConfig['output'];
                }
                if (isset($collectionConfig['permalink']) && \is_string($collectionConfig['permalink'])) {
                    $permalink = $collectionConfig['permalink'];
                }
            }

            $collections[$name] = new Collection(
                name: $name,
                documents: $docs,
                permalink: $permalink,
                output: $output,
            );
        }

        return $collections;
    }

    /**
     * @param Document[] $documents
     *
     * @return array<string, Document[]>
     */
    private function groupByCollection(array $documents): array
    {
        $grouped = [];
        foreach ($documents as $doc) {
            if (null !== $doc->collection) {
                $grouped[$doc->collection][] = $doc;
            }
        }

        return $grouped;
    }

    /**
     * @param Document[] &$documents
     */
    private function sortByDateDescending(array &$documents): void
    {
        usort($documents, static function (Document $a, Document $b): int {
            $dateA = $a->date;
            $dateB = $b->date;

            if (null === $dateA && null === $dateB) {
                return 0;
            }
            if (null === $dateA) {
                return 1;
            }
            if (null === $dateB) {
                return -1;
            }

            return $dateB->getTimestamp() <=> $dateA->getTimestamp();
        });
    }
}
