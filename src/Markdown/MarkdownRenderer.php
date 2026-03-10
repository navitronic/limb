<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Exception\RenderException;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

final class MarkdownRenderer
{
    private readonly CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Convert Markdown to HTML.
     */
    public function render(string $markdown): string
    {
        if ('' === trim($markdown)) {
            return '';
        }

        try {
            return $this->converter->convert($markdown)->getContent();
        } catch (CommonMarkException $e) {
            throw new RenderException(\sprintf('Failed to render Markdown: %s', $e->getMessage()), 0, $e);
        }
    }
}
