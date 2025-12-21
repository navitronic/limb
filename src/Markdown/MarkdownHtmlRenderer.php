<?php

namespace Limb\Markdown;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

final class MarkdownHtmlRenderer
{
    private CommonMarkConverter $converter;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->converter = new CommonMarkConverter($config);
    }

    /**
     * @throws CommonMarkException
     */
    public function toHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
