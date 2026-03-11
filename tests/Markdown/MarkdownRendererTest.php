<?php

declare(strict_types=1);

namespace Limb\Tests\Markdown;

use Limb\Markdown\MarkdownRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    #[Test]
    public function itRendersHeadings(): void
    {
        $html = $this->renderer->render('# Hello World');

        self::assertStringContainsString('<h1>Hello World</h1>', $html);
    }

    #[Test]
    public function itRendersParagraphs(): void
    {
        $html = $this->renderer->render('A simple paragraph.');

        self::assertStringContainsString('<p>A simple paragraph.</p>', $html);
    }

    #[Test]
    public function itRendersLists(): void
    {
        $markdown = <<<'MD'
            - Item one
            - Item two
            - Item three
            MD;

        $html = $this->renderer->render($markdown);

        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<li>Item one</li>', $html);
        self::assertStringContainsString('<li>Item two</li>', $html);
        self::assertStringContainsString('<li>Item three</li>', $html);
    }

    #[Test]
    public function itRendersLinks(): void
    {
        $html = $this->renderer->render('[Example](https://example.com)');

        self::assertStringContainsString('<a href="https://example.com">Example</a>', $html);
    }

    #[Test]
    public function itRendersCodeBlocks(): void
    {
        $markdown = "```php\necho 'hello';\n```";

        $html = $this->renderer->render($markdown);

        self::assertStringContainsString('<code', $html);
        self::assertStringContainsString("echo 'hello';", $html);
    }

    #[Test]
    public function itReturnsEmptyForEmptyInput(): void
    {
        $html = $this->renderer->render('');

        self::assertSame('', trim($html));
    }

    #[Test]
    public function itPreservesHtmlInMarkdown(): void
    {
        $markdown = "Some text.\n\n<div class=\"custom\">HTML content</div>\n\nMore text.";

        $html = $this->renderer->render($markdown);

        self::assertStringContainsString('<div class="custom">HTML content</div>', $html);
        self::assertStringContainsString('<p>Some text.</p>', $html);
        self::assertStringContainsString('<p>More text.</p>', $html);
    }

    #[Test]
    public function itRendersMultipleHeadingLevels(): void
    {
        $markdown = "# H1\n\n## H2\n\n### H3";

        $html = $this->renderer->render($markdown);

        self::assertStringContainsString('<h1>H1</h1>', $html);
        self::assertStringContainsString('<h2>H2</h2>', $html);
        self::assertStringContainsString('<h3>H3</h3>', $html);
    }

    #[Test]
    public function itRendersBoldAndItalic(): void
    {
        $html = $this->renderer->render('**bold** and *italic*');

        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<em>italic</em>', $html);
    }
}
