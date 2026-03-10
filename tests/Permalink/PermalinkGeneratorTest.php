<?php

declare(strict_types=1);

namespace App\Tests\Permalink;

use App\Model\Document;
use App\Permalink\PermalinkGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PermalinkGeneratorTest extends TestCase
{
    private PermalinkGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PermalinkGenerator();
    }

    #[Test]
    public function itGeneratesPostUrlFromDatePattern(): void
    {
        $doc = $this->createPost('2026-01-15', 'hello-world');

        $url = $this->generator->generate($doc, '/:year/:month/:day/:title/');

        self::assertSame('/2026/01/15/hello-world/', $url);
    }

    #[Test]
    public function itGeneratesPageUrlFromTitlePattern(): void
    {
        $doc = $this->createPage('about');

        $url = $this->generator->generate($doc, '/:title/');

        self::assertSame('/about/', $url);
    }

    #[Test]
    public function itUsesFrontMatterPermalinkOverride(): void
    {
        $doc = new Document(
            sourcePath: '/site/about.md',
            relativePath: 'about.md',
            frontMatter: ['permalink' => '/custom/path/'],
            rawContent: 'Content.',
            contentType: 'md',
            title: 'About',
            slug: 'about',
            published: true,
        );

        $url = $this->generator->generate($doc, '/:title/');

        self::assertSame('/custom/path/', $url);
    }

    #[Test]
    public function itGeneratesCustomCollectionPermalink(): void
    {
        $doc = new Document(
            sourcePath: '/site/_projects/limb.md',
            relativePath: '_projects/limb.md',
            frontMatter: [],
            rawContent: 'Content.',
            contentType: 'md',
            title: 'Limb',
            slug: 'limb',
            published: true,
            collection: 'projects',
        );

        $url = $this->generator->generate($doc, '/:collection/:title/');

        self::assertSame('/projects/limb/', $url);
    }

    #[Test]
    public function itGeneratesSlugBasedUrl(): void
    {
        $doc = $this->createPost('2026-03-10', 'my-post');

        $url = $this->generator->generate($doc, '/:year/:slug/');

        self::assertSame('/2026/my-post/', $url);
    }

    #[Test]
    public function itHandlesPatternWithNoTrailingSlash(): void
    {
        $doc = $this->createPage('feed');

        $url = $this->generator->generate($doc, '/:title.xml');

        self::assertSame('/feed.xml', $url);
    }

    #[Test]
    public function itHandlesIndexPage(): void
    {
        $doc = $this->createPage('index');

        $url = $this->generator->generate($doc, '/:title/');

        self::assertSame('/index/', $url);
    }

    #[Test]
    public function itPreservesExistingRelativePathForPages(): void
    {
        $doc = new Document(
            sourcePath: '/site/docs/guide.md',
            relativePath: 'docs/guide.md',
            frontMatter: [],
            rawContent: 'Content.',
            contentType: 'md',
            title: 'Guide',
            slug: 'guide',
            published: true,
        );

        $url = $this->generator->generate($doc, '/:title/');

        self::assertSame('/guide/', $url);
    }

    private function createPost(string $date, string $slug): Document
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        \assert(false !== $dateObj);

        return new Document(
            sourcePath: '/site/_posts/'.$date.'-'.$slug.'.md',
            relativePath: '_posts/'.$date.'-'.$slug.'.md',
            frontMatter: [],
            rawContent: 'Content.',
            contentType: 'md',
            title: $slug,
            slug: $slug,
            published: true,
            collection: 'posts',
            date: $dateObj->setTime(0, 0),
        );
    }

    private function createPage(string $slug): Document
    {
        return new Document(
            sourcePath: '/site/'.$slug.'.md',
            relativePath: $slug.'.md',
            frontMatter: [],
            rawContent: 'Content.',
            contentType: 'md',
            title: $slug,
            slug: $slug,
            published: true,
        );
    }
}
