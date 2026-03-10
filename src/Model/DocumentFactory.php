<?php

declare(strict_types=1);

namespace App\Model;

use App\FrontMatter\ParsedContent;

final class DocumentFactory
{
    private const string POST_FILENAME_PATTERN = '/^(\d{4}-\d{2}-\d{2})-(.+)$/';

    public function createPage(string $sourcePath, string $relativePath, ParsedContent $parsed): Document
    {
        return new Document(
            sourcePath: $sourcePath,
            relativePath: $relativePath,
            frontMatter: $parsed->metadata,
            rawContent: $parsed->body,
            contentType: $this->resolveContentType($sourcePath),
            title: $this->resolveString($parsed->metadata, 'title'),
            slug: $this->resolveSlug($parsed->metadata, $sourcePath),
            published: $this->resolvePublished($parsed->metadata),
            layoutName: $this->resolveNullableString($parsed->metadata, 'layout'),
        );
    }

    public function createPost(string $sourcePath, string $relativePath, ParsedContent $parsed): Document
    {
        $filename = pathinfo($sourcePath, \PATHINFO_FILENAME);
        $filenameDate = null;
        $filenameSlug = $filename;

        if (1 === preg_match(self::POST_FILENAME_PATTERN, $filename, $matches)) {
            $filenameDate = $matches[1];
            $filenameSlug = $matches[2];
        }

        return new Document(
            sourcePath: $sourcePath,
            relativePath: $relativePath,
            frontMatter: $parsed->metadata,
            rawContent: $parsed->body,
            contentType: $this->resolveContentType($sourcePath),
            title: $this->resolveString($parsed->metadata, 'title'),
            slug: $this->resolvePostSlug($parsed->metadata, $filenameSlug),
            published: $this->resolvePublished($parsed->metadata),
            layoutName: $this->resolveNullableString($parsed->metadata, 'layout'),
            collection: 'posts',
            date: $this->resolveDate($parsed->metadata, $filenameDate),
        );
    }

    public function createDraft(string $sourcePath, string $relativePath, ParsedContent $parsed): Document
    {
        $filename = pathinfo($sourcePath, \PATHINFO_FILENAME);

        return new Document(
            sourcePath: $sourcePath,
            relativePath: $relativePath,
            frontMatter: $parsed->metadata,
            rawContent: $parsed->body,
            contentType: $this->resolveContentType($sourcePath),
            title: $this->resolveString($parsed->metadata, 'title'),
            slug: $this->resolvePostSlug($parsed->metadata, $filename),
            published: false,
            layoutName: $this->resolveNullableString($parsed->metadata, 'layout'),
            collection: 'posts',
            date: $this->resolveDate($parsed->metadata, null),
        );
    }

    public function createCollectionDocument(string $sourcePath, string $relativePath, ParsedContent $parsed, string $collectionName): Document
    {
        return new Document(
            sourcePath: $sourcePath,
            relativePath: $relativePath,
            frontMatter: $parsed->metadata,
            rawContent: $parsed->body,
            contentType: $this->resolveContentType($sourcePath),
            title: $this->resolveString($parsed->metadata, 'title'),
            slug: $this->resolveSlug($parsed->metadata, $sourcePath),
            published: $this->resolvePublished($parsed->metadata),
            layoutName: $this->resolveNullableString($parsed->metadata, 'layout'),
            collection: $collectionName,
            date: $this->resolveDate($parsed->metadata, null),
        );
    }

    private function resolveContentType(string $sourcePath): string
    {
        $ext = strtolower(pathinfo($sourcePath, \PATHINFO_EXTENSION));

        return match ($ext) {
            'md', 'markdown' => 'md',
            default => 'html',
        };
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private function resolveString(array $frontMatter, string $key): string
    {
        if (isset($frontMatter[$key]) && \is_string($frontMatter[$key])) {
            return $frontMatter[$key];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private function resolveNullableString(array $frontMatter, string $key): ?string
    {
        if (isset($frontMatter[$key]) && \is_string($frontMatter[$key])) {
            return $frontMatter[$key];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private function resolvePublished(array $frontMatter): bool
    {
        if (isset($frontMatter['published']) && \is_bool($frontMatter['published'])) {
            return $frontMatter['published'];
        }

        return true;
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private function resolveSlug(array $frontMatter, string $sourcePath): string
    {
        if (isset($frontMatter['slug']) && \is_string($frontMatter['slug'])) {
            return $frontMatter['slug'];
        }

        return pathinfo($sourcePath, \PATHINFO_FILENAME);
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private function resolvePostSlug(array $frontMatter, string $filenameSlug): string
    {
        if (isset($frontMatter['slug']) && \is_string($frontMatter['slug'])) {
            return $frontMatter['slug'];
        }

        return $filenameSlug;
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private function resolveDate(array $frontMatter, ?string $filenameDate): ?\DateTimeInterface
    {
        if (isset($frontMatter['date'])) {
            $dateValue = $frontMatter['date'];
            if ($dateValue instanceof \DateTimeInterface) {
                return $dateValue;
            }
            if (\is_string($dateValue)) {
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $dateValue);
                if (false !== $parsed) {
                    return $parsed->setTime(0, 0);
                }
            }
        }

        if (null !== $filenameDate) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $filenameDate);
            if (false !== $parsed) {
                return $parsed->setTime(0, 0);
            }
        }

        return null;
    }
}
