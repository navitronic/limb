<?php

declare(strict_types=1);

namespace App\Permalink;

use App\Exception\ConfigException;
use App\Model\Document;

final class PermalinkGenerator
{
    /**
     * Generate a URL for a document based on a permalink pattern.
     *
     * If the document has a `permalink` key in its front matter, that value is used as-is.
     * Otherwise, tokens in the pattern are replaced with document attributes.
     *
     * Supported tokens: :year, :month, :day, :title, :slug, :collection
     */
    public function generate(Document $doc, string $pattern): string
    {
        if (isset($doc->frontMatter['permalink']) && \is_string($doc->frontMatter['permalink'])) {
            return $doc->frontMatter['permalink'];
        }

        $tokens = [
            ':year' => $doc->date?->format('Y') ?? '0000',
            ':month' => $doc->date?->format('m') ?? '00',
            ':day' => $doc->date?->format('d') ?? '00',
            ':title' => $doc->slug,
            ':slug' => $doc->slug,
            ':collection' => $doc->collection ?? '',
        ];

        $this->validateTokens($pattern, array_keys($tokens));

        return str_replace(array_keys($tokens), array_values($tokens), $pattern);
    }

    /**
     * @param list<string> $validTokens
     */
    private function validateTokens(string $pattern, array $validTokens): void
    {
        preg_match_all('/:[a-z_]+/', $pattern, $matches);

        foreach ($matches[0] as $token) {
            if (!\in_array($token, $validTokens, true)) {
                throw new ConfigException(\sprintf(
                    'Unknown permalink token "%s" in pattern "%s". Valid tokens: %s',
                    $token,
                    $pattern,
                    implode(', ', $validTokens),
                ));
            }
        }
    }
}
