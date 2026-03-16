<?php

declare(strict_types=1);

namespace Limb\Tests\Archive;

use Limb\Archive\ArchiveGenerator;
use Limb\Config\ArchiveConfig;
use Limb\Model\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArchiveGeneratorTest extends TestCase
{
    #[Test]
    public function itReturnsEmptyWhenDisabled(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: false);

        $result = $generator->generate([$this->createPost('2026-03-10')], $config);

        self::assertSame([], $result);
    }

    #[Test]
    public function itGeneratesYearAndMonthArchives(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true, layout: 'archive');

        $posts = [
            $this->createPost('2026-01-10', 'January Post'),
            $this->createPost('2026-01-20', 'Another January Post'),
            $this->createPost('2026-03-15', 'March Post'),
        ];

        $result = $generator->generate($posts, $config);

        $urls = array_map(static fn (Document $d): string => $d->url, $result);

        self::assertContains('/2026/', $urls);
        self::assertContains('/2026/01/', $urls);
        self::assertContains('/2026/03/', $urls);
        self::assertCount(3, $result);
    }

    #[Test]
    public function itGeneratesArchivesAcrossMultipleYears(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true);

        $posts = [
            $this->createPost('2025-06-01', 'Old Post'),
            $this->createPost('2026-03-10', 'New Post'),
        ];

        $result = $generator->generate($posts, $config);

        $urls = array_map(static fn (Document $d): string => $d->url, $result);

        self::assertContains('/2025/', $urls);
        self::assertContains('/2025/06/', $urls);
        self::assertContains('/2026/', $urls);
        self::assertContains('/2026/03/', $urls);
        self::assertCount(4, $result);
    }

    #[Test]
    public function yearArchiveContainsAllPostsForThatYear(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true);

        $posts = [
            $this->createPost('2026-01-10', 'Jan Post'),
            $this->createPost('2026-03-15', 'Mar Post'),
        ];

        $result = $generator->generate($posts, $config);

        $yearDoc = null;
        foreach ($result as $doc) {
            if ('/2026/' === $doc->url) {
                $yearDoc = $doc;

                break;
            }
        }

        self::assertNotNull($yearDoc);
        self::assertCount(2, $yearDoc->frontMatter['archive_posts']);
        self::assertSame(2026, $yearDoc->frontMatter['archive_year']);
        self::assertArrayNotHasKey('archive_month', $yearDoc->frontMatter);
    }

    #[Test]
    public function monthArchiveContainsOnlyPostsForThatMonth(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true);

        $posts = [
            $this->createPost('2026-01-10', 'Jan Post'),
            $this->createPost('2026-01-20', 'Another Jan Post'),
            $this->createPost('2026-03-15', 'Mar Post'),
        ];

        $result = $generator->generate($posts, $config);

        $janDoc = null;
        foreach ($result as $doc) {
            if ('/2026/01/' === $doc->url) {
                $janDoc = $doc;

                break;
            }
        }

        self::assertNotNull($janDoc);
        self::assertCount(2, $janDoc->frontMatter['archive_posts']);
        self::assertSame(2026, $janDoc->frontMatter['archive_year']);
        self::assertSame(1, $janDoc->frontMatter['archive_month']);
    }

    #[Test]
    public function itUsesConfiguredLayout(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true, layout: 'custom-archive');

        $posts = [$this->createPost('2026-01-10')];

        $result = $generator->generate($posts, $config);

        foreach ($result as $doc) {
            self::assertSame('custom-archive', $doc->layoutName);
        }
    }

    #[Test]
    public function itSkipsPostsWithoutDates(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true);

        $postWithDate = $this->createPost('2026-01-10', 'Dated Post');
        $postWithoutDate = new Document(
            sourcePath: '/test/no-date.md',
            relativePath: '_posts/no-date.md',
            frontMatter: ['title' => 'No Date'],
            rawContent: 'content',
            contentType: 'md',
            title: 'No Date',
            slug: 'no-date',
            published: true,
            collection: 'posts',
            date: null,
        );

        $result = $generator->generate([$postWithDate, $postWithoutDate], $config);

        self::assertCount(2, $result);

        $yearDoc = null;
        foreach ($result as $doc) {
            if ('/2026/' === $doc->url) {
                $yearDoc = $doc;

                break;
            }
        }

        self::assertNotNull($yearDoc);
        self::assertCount(1, $yearDoc->frontMatter['archive_posts']);
    }

    #[Test]
    public function archivePostsAreSortedNewestFirst(): void
    {
        $generator = new ArchiveGenerator();
        $config = new ArchiveConfig(enabled: true);

        $posts = [
            $this->createPost('2026-01-05', 'Early'),
            $this->createPost('2026-01-25', 'Late'),
            $this->createPost('2026-01-15', 'Middle'),
        ];

        $result = $generator->generate($posts, $config);

        $janDoc = null;
        foreach ($result as $doc) {
            if ('/2026/01/' === $doc->url) {
                $janDoc = $doc;

                break;
            }
        }

        self::assertNotNull($janDoc);
        $titles = array_map(static fn (Document $d): string => $d->title, $janDoc->frontMatter['archive_posts']);
        self::assertSame(['Late', 'Middle', 'Early'], $titles);
    }

    private function createPost(string $date, string $title = 'Test Post'): Document
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        self::assertNotFalse($dateObj);

        $slug = strtolower(str_replace(' ', '-', $title));

        return new Document(
            sourcePath: '/test/_posts/'.$date.'-'.$slug.'.md',
            relativePath: '_posts/'.$date.'-'.$slug.'.md',
            frontMatter: ['title' => $title, 'layout' => 'post'],
            rawContent: 'content',
            contentType: 'md',
            title: $title,
            slug: $slug,
            published: true,
            collection: 'posts',
            date: $dateObj->setTime(0, 0),
        );
    }
}
