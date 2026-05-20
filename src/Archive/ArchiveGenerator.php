<?php

declare(strict_types=1);

namespace Limb\Archive;

use Limb\Config\ArchiveConfig;
use Limb\Model\Document;

final class ArchiveGenerator
{
    /**
     * Generate archive documents (year and year/month) from posts.
     *
     * @param Document[] $posts
     *
     * @return Document[]
     */
    public function generate(array $posts, ArchiveConfig $config): array
    {
        if (!$config->enabled) {
            return [];
        }

        $grouped = $this->groupPostsByDate($posts);
        $documents = [];

        foreach ($grouped as $year => $months) {
            $yearPosts = [];
            foreach ($months as $monthPosts) {
                $yearPosts = array_merge($yearPosts, $monthPosts);
            }

            $this->sortByDateDescending($yearPosts);

            $documents[] = $this->createArchiveDocument(
                title: (string) $year,
                url: \sprintf('/%d/', $year),
                layout: $config->layout,
                archivePosts: $yearPosts,
                archiveYear: $year,
            );

            foreach ($months as $month => $monthPosts) {
                $this->sortByDateDescending($monthPosts);

                $documents[] = $this->createArchiveDocument(
                    title: \sprintf('%d/%02d', $year, $month),
                    url: \sprintf('/%d/%02d/', $year, $month),
                    layout: $config->layout,
                    archivePosts: $monthPosts,
                    archiveYear: $year,
                    archiveMonth: $month,
                );
            }
        }

        return $documents;
    }

    /**
     * @param Document[] $posts
     *
     * @return array<int, array<int, Document[]>>
     */
    private function groupPostsByDate(array $posts): array
    {
        $grouped = [];

        foreach ($posts as $post) {
            if (null === $post->date) {
                continue;
            }

            $year = (int) $post->date->format('Y');
            $month = (int) $post->date->format('n');

            $grouped[$year][$month][] = $post;
        }

        krsort($grouped);
        foreach ($grouped as &$months) {
            krsort($months);
        }

        return $grouped;
    }

    /**
     * @param Document[] $archivePosts
     */
    private function createArchiveDocument(
        string $title,
        string $url,
        string $layout,
        array $archivePosts,
        int $archiveYear,
        ?int $archiveMonth = null,
    ): Document {
        $frontMatter = [
            'title' => $title,
            'layout' => $layout,
            'archive_year' => $archiveYear,
            'archive_posts' => $archivePosts,
        ];

        if (null !== $archiveMonth) {
            $frontMatter['archive_month'] = $archiveMonth;
        }

        return new Document(
            sourcePath: '',
            relativePath: '',
            frontMatter: $frontMatter,
            rawContent: '',
            contentType: 'html',
            title: $title,
            slug: trim($url, '/'),
            published: true,
            layoutName: $layout,
            url: $url,
        );
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
