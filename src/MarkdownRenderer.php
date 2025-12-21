<?php

declare(strict_types=1);

namespace Limb;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterParserInterface;

final class MarkdownRenderer
{
    private CommonMarkConverter $converter;
    private FrontMatterParserInterface $frontMatterParser;

    /**
     * @param array<string, mixed> $config
     *
     * @throws AlreadyInitializedException
     */
    public function __construct(array $config = [])
    {
        $this->converter = new CommonMarkConverter($config);
        $frontMatterExtension = new FrontMatterExtension();
        $this->converter->getEnvironment()->addExtension($frontMatterExtension);
        $this->frontMatterParser = $frontMatterExtension->getFrontMatterParser();
    }

    /**
     * @throws CommonMarkException
     */
    public function toHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }

    /**
     * @return Limb|null Returns null when markdown cannot be parsed.
     */
    public function parse(string $markdown, ?string $sourcePath = null): ?Limb
    {
        try {
            $parsed = $this->frontMatterParser->parse($markdown);
            $content = $parsed->getContent();
            $metadata = $this->normalizeMetadata($parsed->getFrontMatter());
            $title = $this->resolveTitle($metadata, $content, $sourcePath);
            $slug = $this->resolveSlug($metadata, $sourcePath, $title);
            $createdAt = $this->resolveDate($metadata, ['created_at', 'createdAt', 'date']);
            $updatedAt = $this->resolveDate($metadata, ['updated_at', 'updatedAt', 'updated']);
            $html = $this->converter->convert($content)->getContent();

            if ($createdAt === null) {
                $createdAt = $updatedAt ?? new \DateTime();
            }

            return new Limb($title, $slug, $createdAt, $updatedAt, $content, $html, $metadata);
        } catch (CommonMarkException | InvalidFrontMatterException) {
            return null;
        }
    }

    /**
     * @return Limb|null Returns null when markdown cannot be parsed.
     *
     * @throws \RuntimeException
     */
    public function parseFile(string $path): ?Limb
    {
        $contents = \file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read markdown file: {$path}");
        }

        return $this->parse($contents, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $frontMatter): array
    {
        if (!\is_array($frontMatter)) {
            return [];
        }

        $metadata = [];
        foreach (\array_keys($frontMatter) as $key) {
            if (!\is_string($key)) {
                continue;
            }

            $metadata[$key] = $frontMatter[$key];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveTitle(array $metadata, string $content, ?string $sourcePath): string
    {
        if (\array_key_exists('title', $metadata) && \is_string($metadata['title'])) {
            $titleValue = \trim($metadata['title']);
            if ($titleValue !== '') {
                return $titleValue;
            }
        }

        $matches = null;
        if (\preg_match('/^#{1,6}\s+(.+?)\s*#*\s*$/m', $content, $matches) === 1) {
            return \trim($matches[1]);
        }

        if ($sourcePath !== null) {
            $filename = \pathinfo($sourcePath, \PATHINFO_FILENAME);
            if ($filename !== '') {
                return $this->humanizeFilename($filename);
            }
        }

        return 'Untitled';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveSlug(array $metadata, ?string $sourcePath, string $title): string
    {
        if (\array_key_exists('slug', $metadata) && \is_string($metadata['slug'])) {
            $slugValue = \trim($metadata['slug']);
            if ($slugValue !== '') {
                return $slugValue;
            }
        }

        if ($sourcePath !== null) {
            $filename = \pathinfo($sourcePath, \PATHINFO_FILENAME);
            if ($filename !== '') {
                return $this->slugify($filename);
            }
        }

        return $this->slugify($title);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, string> $keys
     */
    private function resolveDate(array $metadata, array $keys): ?\DateTime
    {
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $metadata)) {
                continue;
            }

            $parsed = $this->parseDateValue($metadata[$key]);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
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
            $trimmed = \trim($value);
            if ($trimmed === '') {
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
        $normalized = \preg_replace('/[^a-zA-Z0-9]+/', '-', $value);
        $normalized = \trim($normalized ?? '', '-');

        if ($normalized === '') {
            return 'untitled';
        }

        return \strtolower($normalized);
    }

    private function humanizeFilename(string $filename): string
    {
        $label = \str_replace(['-', '_'], ' ', $filename);
        $label = \trim($label);

        if ($label === '') {
            return 'Untitled';
        }

        return \ucwords($label);
    }
}
