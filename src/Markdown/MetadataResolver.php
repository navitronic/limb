<?php

declare(strict_types=1);

namespace Limb\Markdown;

final class MetadataResolver
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function resolve(array $metadata, string $content, ?string $sourcePath): ResolvedMetadata
    {
        $assignableKeys = [];
        [$title, $assignableTitle] = $this->resolveTitle($metadata, $content, $sourcePath);
        if ($assignableTitle) {
            $assignableKeys[] = 'title';
        }

        [$slug, $assignableSlug] = $this->resolveSlug($metadata, $sourcePath, $title);
        if ($assignableSlug) {
            $assignableKeys[] = 'slug';
        }

        [$createdAt, $createdKeys] = $this->resolveDate($metadata, ['created_at', 'createdAt', 'date']);
        $assignableKeys = array_merge($assignableKeys, $createdKeys);

        [$updatedAt, $updatedKeys] = $this->resolveDate($metadata, ['updated_at', 'updatedAt', 'updated']);
        $assignableKeys = array_merge($assignableKeys, $updatedKeys);

        if (null === $createdAt) {
            $createdAt = $updatedAt ?? new \DateTime();
        }

        $extra = $this->resolveExtra($metadata, $assignableKeys);

        return new ResolvedMetadata($title, $slug, $createdAt, $updatedAt, $extra);
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{0: string, 1: bool}
     */
    private function resolveTitle(array $metadata, string $content, ?string $sourcePath): array
    {
        if (\array_key_exists('title', $metadata) && \is_string($metadata['title'])) {
            $titleValue = trim($metadata['title']);
            if ('' !== $titleValue) {
                return [$titleValue, true];
            }
        }

        $matches = null;
        if (1 === preg_match('/^#{1,6}\s+(.+?)\s*#*\s*$/m', $content, $matches)) {
            return [trim($matches[1]), false];
        }

        if (null !== $sourcePath) {
            $filename = pathinfo($sourcePath, \PATHINFO_FILENAME);
            if ('' !== $filename) {
                return [$this->humanizeFilename($filename), false];
            }
        }

        return ['Untitled', false];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{0: string, 1: bool}
     */
    private function resolveSlug(array $metadata, ?string $sourcePath, string $title): array
    {
        if (\array_key_exists('slug', $metadata) && \is_string($metadata['slug'])) {
            $slugValue = trim($metadata['slug']);
            if ('' !== $slugValue) {
                return [$slugValue, true];
            }
        }

        if (null !== $sourcePath) {
            $filename = pathinfo($sourcePath, \PATHINFO_FILENAME);
            if ('' !== $filename) {
                return [$this->slugify($filename), false];
            }
        }

        return [$this->slugify($title), false];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, string>   $keys
     *
     * @return array{0: ?\DateTime, 1: array<int, string>}
     */
    private function resolveDate(array $metadata, array $keys): array
    {
        $resolved = null;
        $assignableKeys = [];

        foreach ($keys as $key) {
            if (!\array_key_exists($key, $metadata)) {
                continue;
            }

            $parsed = $this->parseDateValue($metadata[$key]);
            if (null !== $parsed) {
                $assignableKeys[] = $key;
                if (null === $resolved) {
                    $resolved = $parsed;
                }
            }
        }

        return [$resolved, $assignableKeys];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, string>   $assignableKeys
     *
     * @return array<string, mixed>
     */
    private function resolveExtra(array $metadata, array $assignableKeys): array
    {
        if ([] === $assignableKeys) {
            return $metadata;
        }

        $extra = $metadata;
        foreach (array_unique($assignableKeys) as $key) {
            unset($extra[$key]);
        }

        return $extra;
    }

    private function parseDateValue(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTime($value->format(\DateTimeInterface::ATOM));
        }

        if (\is_int($value)) {
            return new \DateTime()->setTimestamp($value);
        }

        if (\is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed) {
                return null;
            }

            try {
                return new \DateTime($trimmed);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private function slugify(string $value): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', '-', $value);
        $normalized = trim($normalized ?? '', '-');

        if ('' === $normalized) {
            return 'untitled';
        }

        return strtolower($normalized);
    }

    private function humanizeFilename(string $filename): string
    {
        $label = str_replace(['-', '_'], ' ', $filename);
        $label = trim($label);

        if ('' === $label) {
            return 'Untitled';
        }

        return ucwords($label);
    }
}
