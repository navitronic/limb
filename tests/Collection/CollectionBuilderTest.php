<?php

declare(strict_types=1);

namespace App\Tests\Collection;

use App\Collection\CollectionBuilder;
use App\Config\SiteConfig;
use App\Model\Collection;
use App\Model\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionBuilderTest extends TestCase
{
    private CollectionBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CollectionBuilder();
    }

    #[Test]
    public function itGroupsDocumentsByCollection(): void
    {
        $postA = $this->createDocument('a.md', collection: 'posts', date: '2026-01-01');
        $postB = $this->createDocument('b.md', collection: 'posts', date: '2026-01-02');
        $project = $this->createDocument('proj.md', collection: 'projects');

        $config = new SiteConfig();
        $collections = $this->builder->build([$postA, $postB, $project], $config);

        self::assertArrayHasKey('posts', $collections);
        self::assertArrayHasKey('projects', $collections);
        self::assertCount(2, $collections['posts']->documents);
        self::assertCount(1, $collections['projects']->documents);
    }

    #[Test]
    public function itSortsPostsByDateNewestFirst(): void
    {
        $old = $this->createDocument('old.md', collection: 'posts', date: '2025-06-01');
        $new = $this->createDocument('new.md', collection: 'posts', date: '2026-03-15');
        $mid = $this->createDocument('mid.md', collection: 'posts', date: '2025-12-25');

        $config = new SiteConfig();
        $collections = $this->builder->build([$old, $mid, $new], $config);

        $docs = $collections['posts']->documents;
        self::assertSame('new.md', $docs[0]->relativePath);
        self::assertSame('mid.md', $docs[1]->relativePath);
        self::assertSame('old.md', $docs[2]->relativePath);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoCollections(): void
    {
        $config = new SiteConfig();
        $collections = $this->builder->build([], $config);

        self::assertSame([], $collections);
    }

    #[Test]
    public function itIgnoresDocumentsWithNoCollection(): void
    {
        $page = $this->createDocument('about.md');
        $post = $this->createDocument('hello.md', collection: 'posts', date: '2026-01-01');

        $config = new SiteConfig();
        $collections = $this->builder->build([$page, $post], $config);

        self::assertCount(1, $collections);
        self::assertArrayHasKey('posts', $collections);
    }

    #[Test]
    public function itSetsOutputFlagFromConfig(): void
    {
        $doc = $this->createDocument('draft.md', collection: 'drafts');

        $config = new SiteConfig(
            collections: [
                'drafts' => ['output' => false],
            ],
        );
        $collections = $this->builder->build([$doc], $config);

        self::assertFalse($collections['drafts']->output);
    }

    #[Test]
    public function itSetsPermalinkFromConfig(): void
    {
        $doc = $this->createDocument('proj.md', collection: 'projects');

        $config = new SiteConfig(
            collections: [
                'projects' => ['permalink' => '/:collection/:title/'],
            ],
        );
        $collections = $this->builder->build([$doc], $config);

        self::assertSame('/:collection/:title/', $collections['projects']->permalink);
    }

    #[Test]
    public function itCreatesCollectionModelInstances(): void
    {
        $doc = $this->createDocument('hello.md', collection: 'posts', date: '2026-01-01');

        $config = new SiteConfig();
        $collections = $this->builder->build([$doc], $config);

        self::assertInstanceOf(Collection::class, $collections['posts']);
        self::assertSame('posts', $collections['posts']->name);
    }

    private function createDocument(
        string $relativePath,
        ?string $collection = null,
        ?string $date = null,
    ): Document {
        return new Document(
            sourcePath: '/site/'.$relativePath,
            relativePath: $relativePath,
            frontMatter: [],
            rawContent: 'Content',
            contentType: 'md',
            title: pathinfo($relativePath, \PATHINFO_FILENAME),
            slug: pathinfo($relativePath, \PATHINFO_FILENAME),
            published: true,
            collection: $collection,
            date: null !== $date ? new \DateTimeImmutable($date) : null,
        );
    }
}
