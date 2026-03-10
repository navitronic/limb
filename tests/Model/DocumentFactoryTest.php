<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\FrontMatter\ParsedContent;
use App\Model\DocumentFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentFactoryTest extends TestCase
{
    private DocumentFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DocumentFactory();
    }

    #[Test]
    public function itCreatesDocumentFromPage(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'About', 'layout' => 'default'],
            'About page content.',
            true,
        );

        $doc = $this->factory->createPage(
            '/site/about.md',
            'about.md',
            $parsed,
        );

        self::assertSame('/site/about.md', $doc->sourcePath);
        self::assertSame('about.md', $doc->relativePath);
        self::assertSame('About', $doc->title);
        self::assertSame('default', $doc->layoutName);
        self::assertSame('About page content.', $doc->rawContent);
        self::assertSame('md', $doc->contentType);
        self::assertTrue($doc->published);
        self::assertNull($doc->collection);
        self::assertNull($doc->date);
        self::assertSame('about', $doc->slug);
    }

    #[Test]
    public function itCreatesDocumentFromPost(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Hello World', 'layout' => 'post'],
            'Post body content.',
            true,
        );

        $doc = $this->factory->createPost(
            '/site/_posts/2026-01-15-hello-world.md',
            '_posts/2026-01-15-hello-world.md',
            $parsed,
        );

        self::assertSame('Hello World', $doc->title);
        self::assertSame('post', $doc->layoutName);
        self::assertSame('Post body content.', $doc->rawContent);
        self::assertSame('md', $doc->contentType);
        self::assertSame('posts', $doc->collection);
        self::assertSame('hello-world', $doc->slug);
        self::assertNotNull($doc->date);
        self::assertSame('2026-01-15', $doc->date->format('Y-m-d'));
    }

    #[Test]
    public function itExtractsDateAndSlugFromPostFilename(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Test'],
            'Body.',
            true,
        );

        $doc = $this->factory->createPost(
            '/site/_posts/2026-03-10-my-post.md',
            '_posts/2026-03-10-my-post.md',
            $parsed,
        );

        self::assertSame('my-post', $doc->slug);
        self::assertNotNull($doc->date);
        self::assertSame('2026-03-10', $doc->date->format('Y-m-d'));
    }

    #[Test]
    public function itUsesSlugFromFrontMatterOverFilename(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Test', 'slug' => 'custom-slug'],
            'Body.',
            true,
        );

        $doc = $this->factory->createPost(
            '/site/_posts/2026-01-15-original-slug.md',
            '_posts/2026-01-15-original-slug.md',
            $parsed,
        );

        self::assertSame('custom-slug', $doc->slug);
    }

    #[Test]
    public function itUsesDateFromFrontMatterOverFilename(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Test', 'date' => '2026-06-01'],
            'Body.',
            true,
        );

        $doc = $this->factory->createPost(
            '/site/_posts/2026-01-15-test.md',
            '_posts/2026-01-15-test.md',
            $parsed,
        );

        self::assertNotNull($doc->date);
        self::assertSame('2026-06-01', $doc->date->format('Y-m-d'));
    }

    #[Test]
    public function itSetsPublishedFalseFromFrontMatter(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Draft', 'published' => false],
            'Draft content.',
            true,
        );

        $doc = $this->factory->createPage(
            '/site/draft.md',
            'draft.md',
            $parsed,
        );

        self::assertFalse($doc->published);
    }

    #[Test]
    public function itDefaultsPublishedToTrue(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Normal'],
            'Content.',
            true,
        );

        $doc = $this->factory->createPage(
            '/site/normal.md',
            'normal.md',
            $parsed,
        );

        self::assertTrue($doc->published);
    }

    #[Test]
    public function itDetectsHtmlContentType(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'HTML Page'],
            '<p>HTML content</p>',
            true,
        );

        $doc = $this->factory->createPage(
            '/site/page.html',
            'page.html',
            $parsed,
        );

        self::assertSame('html', $doc->contentType);
    }

    #[Test]
    public function itDetectsMarkdownContentType(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Markdown Page'],
            '# Content',
            true,
        );

        $doc = $this->factory->createPage(
            '/site/page.markdown',
            'page.markdown',
            $parsed,
        );

        self::assertSame('md', $doc->contentType);
    }

    #[Test]
    public function itCreatesDraftDocument(): void
    {
        $parsed = new ParsedContent(
            ['title' => 'Draft Post'],
            'Draft body.',
            true,
        );

        $doc = $this->factory->createDraft(
            '/site/_drafts/upcoming-post.md',
            '_drafts/upcoming-post.md',
            $parsed,
        );

        self::assertFalse($doc->published);
        self::assertSame('posts', $doc->collection);
        self::assertSame('upcoming-post', $doc->slug);
    }

    #[Test]
    public function itHandlesPageWithNoFrontMatter(): void
    {
        $parsed = new ParsedContent(
            [],
            'Just plain content.',
            false,
        );

        $doc = $this->factory->createPage(
            '/site/plain.md',
            'plain.md',
            $parsed,
        );

        self::assertSame('', $doc->title);
        self::assertNull($doc->layoutName);
        self::assertSame('Just plain content.', $doc->rawContent);
        self::assertTrue($doc->published);
    }

    #[Test]
    public function itPreservesFrontMatterArray(): void
    {
        $frontMatter = ['title' => 'Test', 'custom_key' => 'custom_value', 'tags' => ['a', 'b']];
        $parsed = new ParsedContent($frontMatter, 'Body.', true);

        $doc = $this->factory->createPage(
            '/site/test.md',
            'test.md',
            $parsed,
        );

        self::assertSame($frontMatter, $doc->frontMatter);
        self::assertSame('custom_value', $doc->frontMatter['custom_key']);
    }
}
