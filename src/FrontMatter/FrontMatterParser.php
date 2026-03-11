<?php

declare(strict_types=1);

namespace Limb\FrontMatter;

use Limb\Exception\FrontMatterException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class FrontMatterParser
{
    private const string DELIMITER = '---';

    /**
     * Parse front matter from file content.
     *
     * Front matter must appear at the very start of the file, delimited by `---` lines.
     * Only the first `---` block is treated as front matter.
     */
    public function parse(string $content, string $filePath): ParsedContent
    {
        $normalized = str_replace("\r\n", "\n", $content);

        if (!str_starts_with($normalized, self::DELIMITER."\n") && self::DELIMITER !== $normalized) {
            return new ParsedContent([], $content, false);
        }

        $afterOpening = substr($normalized, \strlen(self::DELIMITER) + 1);

        // Handle empty front matter: closing --- is immediately after opening
        if (str_starts_with($afterOpening, self::DELIMITER."\n")) {
            $yamlString = '';
            $body = substr($afterOpening, \strlen(self::DELIMITER) + 1);
        } elseif (self::DELIMITER === $afterOpening) {
            $yamlString = '';
            $body = '';
        } else {
            $closingPos = strpos($afterOpening, "\n".self::DELIMITER);

            if (false === $closingPos) {
                // Check if the closing delimiter is at the very end without trailing newline
                if (str_ends_with($afterOpening, self::DELIMITER) && str_ends_with(substr($afterOpening, 0, -\strlen(self::DELIMITER)), "\n")) {
                    $yamlString = substr($afterOpening, 0, -\strlen(self::DELIMITER));
                    $body = '';
                } else {
                    return new ParsedContent([], $content, false);
                }
            } else {
                $yamlString = substr($afterOpening, 0, $closingPos);
                $body = substr($afterOpening, $closingPos + \strlen("\n".self::DELIMITER) + 1);
            }
        }

        $metadata = $this->parseYaml($yamlString, $filePath);

        return new ParsedContent($metadata, $body, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $yaml, string $filePath): array
    {
        $trimmed = trim($yaml);

        if ('' === $trimmed) {
            return [];
        }

        try {
            $parsed = Yaml::parse($trimmed);
        } catch (ParseException $e) {
            throw new FrontMatterException(\sprintf('Invalid YAML in front matter of "%s": %s', $filePath, $e->getMessage()), 0, $e);
        }

        if (!\is_array($parsed)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = $parsed;

        return $result;
    }
}
