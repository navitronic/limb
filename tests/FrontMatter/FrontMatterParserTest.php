<?php

declare(strict_types=1);

namespace App\Tests\FrontMatter;

use App\FrontMatter\FrontMatterParser;
use App\FrontMatter\ParsedContent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FrontMatterParserTest extends TestCase
{
    private FrontMatterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FrontMatterParser();
    }

    #[Test]
    public function itParsesValidFrontMatterWithVariousYamlTypes(): void
    {
        $content = <<<'MD'
            ---
            title: "My Post"
            tags: [php, symfony]
            published: true
            date: 2026-01-15
            count: 42
            ---
            Hello world.
            MD;

        $result = $this->parser->parse($content, 'test.md');

        self::assertInstanceOf(ParsedContent::class, $result);
        self::assertTrue($result->hasFrontMatter);
        self::assertSame('My Post', $result->metadata['title']);
        self::assertSame(['php', 'symfony'], $result->metadata['tags']);
        self::assertTrue($result->metadata['published']);
        self::assertSame(42, $result->metadata['count']);
        self::assertSame('Hello world.', trim($result->body));
    }

    #[Test]
    public function itReturnsFullBodyWhenNoFrontMatter(): void
    {
        $content = "Just some content\nwith multiple lines.";

        $result = $this->parser->parse($content, 'test.md');

        self::assertFalse($result->hasFrontMatter);
        self::assertSame([], $result->metadata);
        self::assertSame($content, $result->body);
    }

    #[Test]
    public function itThrowsOnInvalidYamlInFrontMatter(): void
    {
        $content = <<<'MD'
            ---
            title: "unclosed
            invalid:
              - {
            ---
            Body content.
            MD;

        $this->expectException(\App\Exception\FrontMatterException::class);
        $this->expectExceptionMessageMatches('/Invalid YAML.*front matter.*test\.md/');
        $this->parser->parse($content, 'test.md');
    }

    #[Test]
    public function itHandlesEmptyFrontMatter(): void
    {
        $content = "---\n---\nBody after empty front matter.";

        $result = $this->parser->parse($content, 'test.md');

        self::assertTrue($result->hasFrontMatter);
        self::assertSame([], $result->metadata);
        self::assertSame('Body after empty front matter.', trim($result->body));
    }

    #[Test]
    public function itOnlyTreatsFirstBlockAsFrontMatter(): void
    {
        $content = <<<'MD'
            ---
            title: "Real Front Matter"
            ---
            Some body content.

            ---
            this: "is not front matter"
            ---
            MD;

        $result = $this->parser->parse($content, 'test.md');

        self::assertTrue($result->hasFrontMatter);
        self::assertSame('Real Front Matter', $result->metadata['title']);
        self::assertArrayNotHasKey('this', $result->metadata);
        self::assertStringContainsString('---', $result->body);
        self::assertStringContainsString('this: "is not front matter"', $result->body);
    }

    #[Test]
    public function itRequiresFrontMatterAtStartOfFile(): void
    {
        $content = "Some leading text\n---\ntitle: \"Not Front Matter\"\n---\nBody.";

        $result = $this->parser->parse($content, 'test.md');

        self::assertFalse($result->hasFrontMatter);
        self::assertSame([], $result->metadata);
        self::assertSame($content, $result->body);
    }

    #[Test]
    public function itHandlesWindowsLineEndings(): void
    {
        $content = "---\r\ntitle: \"Windows\"\r\n---\r\nBody content.";

        $result = $this->parser->parse($content, 'test.md');

        self::assertTrue($result->hasFrontMatter);
        self::assertSame('Windows', $result->metadata['title']);
        self::assertSame('Body content.', trim($result->body));
    }

    #[Test]
    public function itHandlesFrontMatterWithNoTrailingBody(): void
    {
        $content = "---\ntitle: \"Only Metadata\"\n---";

        $result = $this->parser->parse($content, 'test.md');

        self::assertTrue($result->hasFrontMatter);
        self::assertSame('Only Metadata', $result->metadata['title']);
        self::assertSame('', trim($result->body));
    }
}
