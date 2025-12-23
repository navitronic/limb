<?php

declare(strict_types=1);

namespace Limb\Tests;

use Limb\Container\LimbContainerFactory;
use Limb\Markdown\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    public function testToHtmlRendersMarkdown(): void
    {
        $renderer = $this->createRenderer();

        $html = $renderer->toHtml("# Hello\n\nWorld");

        $this->assertStringContainsString('<h1>', $html);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringContainsString('<p>World</p>', $html);
    }

    public function testParseReturnsLimbWithMetadataAndHtml(): void
    {
        $renderer = $this->createRenderer();

        $markdown = "---\n" .
            "title: Hello World\n" .
            "tags:\n" .
            "  - intro\n" .
            "created_at: 2024-01-02\n" .
            "updated_at: 2024-01-03\n" .
            "---\n\n" .
            "# Hello World\n\nBody";

        $limb = $renderer->parse($markdown);

        $this->assertNotNull($limb);
        $this->assertSame('Hello World', $limb->title);
        $this->assertSame('hello-world', $limb->slug);
        $this->assertSame('2024-01-02', $limb->createdAt->format('Y-m-d'));
        $this->assertSame('2024-01-03', $limb->updatedAt?->format('Y-m-d'));
        $this->assertSame("# Hello World\n\nBody", $limb->content);
        $this->assertStringContainsString('<h1>', $limb->html);
        $this->assertArrayHasKey('tags', $limb->metadata);
        $this->assertSame(['intro'], $limb->metadata['tags']);
    }

    public function testParseUsesFilenameFallbacks(): void
    {
        $renderer = $this->createRenderer();

        $limb = $renderer->parse("Just text\n", '/tmp/my-first-post.md');

        $this->assertNotNull($limb);
        $this->assertSame('My First Post', $limb->title);
        $this->assertSame('my-first-post', $limb->slug);
    }

    public function testParseReturnsNullOnInvalidFrontMatter(): void
    {
        $renderer = $this->createRenderer();

        $markdown = "---\n" .
            "title: [unclosed\n" .
            "---\n\n" .
            "# Hello";

        $this->assertNull($renderer->parse($markdown));
    }

    public function testParseFileReturnsLimb(): void
    {
        $renderer = $this->createRenderer();
        $path = \tempnam(\sys_get_temp_dir(), 'limb_');

        $this->assertNotFalse($path, 'Failed to create temporary file.');

        $markdown = "---\n" .
            "title: File Post\n" .
            "slug: file-post\n" .
            "---\n\n" .
            "# File Post\n\nBody";

        $contentsWritten = \file_put_contents($path, $markdown);

        $this->assertNotFalse($contentsWritten, 'Failed to write temporary markdown.');

        try {
            $limb = $renderer->parseFile($path);

            $this->assertNotNull($limb);
            $this->assertSame('File Post', $limb->title);
            $this->assertSame('file-post', $limb->slug);
        } finally {
            \unlink($path);
        }
    }

    public function testParseFileThrowsWhenMissing(): void
    {
        $renderer = $this->createRenderer();

        $this->expectException(\RuntimeException::class);

        $renderer->parseFile('/tmp/limb-missing-file-' . \uniqid() . '.md');
    }

    private function createRenderer(): MarkdownRenderer
    {
        $container = LimbContainerFactory::create();

        return $container->get(MarkdownRenderer::class);
    }
}
