<?php

declare(strict_types=1);

namespace App\Tests\Rendering;

use App\Config\SiteConfig;
use App\Markdown\MarkdownRenderer;
use App\Model\Document;
use App\Model\Site;
use App\Rendering\DocumentRenderer;
use App\Rendering\TwigEnvironmentFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentRendererTest extends TestCase
{
    private DocumentRenderer $renderer;
    private Site $site;

    protected function setUp(): void
    {
        $fixtureDir = \dirname(__DIR__).'/Fixtures/basic-site';
        $config = new SiteConfig(
            title: 'Test Site',
            source: $fixtureDir,
        );
        $this->site = new Site(config: $config);

        $twigFactory = new TwigEnvironmentFactory();
        $twig = $twigFactory->create($fixtureDir);
        $markdownRenderer = new MarkdownRenderer();

        $this->renderer = new DocumentRenderer($twig, $markdownRenderer);
    }

    #[Test]
    public function itRendersMarkdownDocumentWithLayout(): void
    {
        $doc = new Document(
            sourcePath: '/site/about.md',
            relativePath: 'about.md',
            frontMatter: ['title' => 'About', 'layout' => 'default'],
            rawContent: '# About Us',
            contentType: 'md',
            title: 'About',
            slug: 'about',
            published: true,
            layoutName: 'default',
        );

        $html = $this->renderer->render($doc, $this->site);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<title>Test Site</title>', $html);
        self::assertStringContainsString('<h1>About Us</h1>', $html);
    }

    #[Test]
    public function itRendersHtmlDocumentWithLayout(): void
    {
        $doc = new Document(
            sourcePath: '/site/page.html',
            relativePath: 'page.html',
            frontMatter: ['title' => 'HTML Page', 'layout' => 'default'],
            rawContent: '<p>Already HTML</p>',
            contentType: 'html',
            title: 'HTML Page',
            slug: 'page',
            published: true,
            layoutName: 'default',
        );

        $html = $this->renderer->render($doc, $this->site);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<p>Already HTML</p>', $html);
    }

    #[Test]
    public function itRendersDocumentWithoutLayout(): void
    {
        $doc = new Document(
            sourcePath: '/site/plain.md',
            relativePath: 'plain.md',
            frontMatter: [],
            rawContent: '# No Layout',
            contentType: 'md',
            title: 'No Layout',
            slug: 'plain',
            published: true,
        );

        $html = $this->renderer->render($doc, $this->site);

        self::assertStringContainsString('<h1>No Layout</h1>', $html);
        self::assertStringNotContainsString('<!DOCTYPE html>', $html);
    }

    #[Test]
    public function itThrowsOnMissingLayout(): void
    {
        $doc = new Document(
            sourcePath: '/site/test.md',
            relativePath: 'test.md',
            frontMatter: ['layout' => 'nonexistent'],
            rawContent: 'Content.',
            contentType: 'md',
            title: 'Test',
            slug: 'test',
            published: true,
            layoutName: 'nonexistent',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/layout.*nonexistent/i');
        $this->renderer->render($doc, $this->site);
    }

    #[Test]
    public function itMakesSiteContextAvailable(): void
    {
        $doc = new Document(
            sourcePath: '/site/test.md',
            relativePath: 'test.md',
            frontMatter: ['title' => 'Test', 'layout' => 'default'],
            rawContent: 'Content.',
            contentType: 'md',
            title: 'Test',
            slug: 'test',
            published: true,
            layoutName: 'default',
        );

        $html = $this->renderer->render($doc, $this->site);

        self::assertStringContainsString('<title>Test Site</title>', $html);
    }

    #[Test]
    public function itRendersWithLayoutChaining(): void
    {
        $doc = new Document(
            sourcePath: '/site/_posts/2026-01-15-hello.md',
            relativePath: '_posts/2026-01-15-hello.md',
            frontMatter: ['title' => 'Hello', 'layout' => 'post'],
            rawContent: 'Post content here.',
            contentType: 'md',
            title: 'Hello',
            slug: 'hello',
            published: true,
            layoutName: 'post',
            collection: 'posts',
            date: new \DateTimeImmutable('2026-01-15'),
        );

        $html = $this->renderer->render($doc, $this->site);

        // post layout wraps in <article> and extends default layout
        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<article>', $html);
        self::assertStringContainsString('<h1>Hello</h1>', $html);
        self::assertStringContainsString('<p>Post content here.</p>', $html);
    }
}
