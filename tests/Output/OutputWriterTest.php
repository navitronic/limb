<?php

declare(strict_types=1);

namespace App\Tests\Output;

use App\Model\Document;
use App\Output\OutputWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutputWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/limb_output_test_'.bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function itWritesRenderedDocumentsToOutputPath(): void
    {
        $doc = $this->createDocument('about.md', $this->tempDir.'/about/index.html', '<h1>About</h1>');

        $writer = new OutputWriter();
        $count = $writer->write([$doc]);

        self::assertSame(1, $count);
        self::assertFileExists($this->tempDir.'/about/index.html');
        self::assertSame('<h1>About</h1>', file_get_contents($this->tempDir.'/about/index.html'));
    }

    #[Test]
    public function itCreatesNestedDirectories(): void
    {
        $doc = $this->createDocument('post.md', $this->tempDir.'/2026/01/15/hello/index.html', '<p>Hello</p>');

        $writer = new OutputWriter();
        $writer->write([$doc]);

        self::assertFileExists($this->tempDir.'/2026/01/15/hello/index.html');
    }

    #[Test]
    public function itWritesMultipleDocuments(): void
    {
        $docs = [
            $this->createDocument('a.md', $this->tempDir.'/a/index.html', 'Page A'),
            $this->createDocument('b.md', $this->tempDir.'/b/index.html', 'Page B'),
            $this->createDocument('c.md', $this->tempDir.'/c/index.html', 'Page C'),
        ];

        $writer = new OutputWriter();
        $count = $writer->write($docs);

        self::assertSame(3, $count);
    }

    #[Test]
    public function itDetectsDuplicateOutputPaths(): void
    {
        $docs = [
            $this->createDocument('page-a.md', $this->tempDir.'/about/index.html', 'A'),
            $this->createDocument('page-b.md', $this->tempDir.'/about/index.html', 'B'),
        ];

        $writer = new OutputWriter();

        $this->expectException(\App\Exception\OutputException::class);
        $this->expectExceptionMessageMatches('/duplicate.*output.*path/i');
        $writer->write($docs);
    }

    #[Test]
    public function itSkipsDocumentsWithNoRenderedContent(): void
    {
        $doc = new Document(
            sourcePath: '/site/skip.md',
            relativePath: 'skip.md',
            frontMatter: [],
            rawContent: 'content',
            contentType: 'md',
            title: 'Skip',
            slug: 'skip',
            published: true,
            outputPath: $this->tempDir.'/skip/index.html',
        );

        $writer = new OutputWriter();
        $count = $writer->write([$doc]);

        self::assertSame(0, $count);
        self::assertFileDoesNotExist($this->tempDir.'/skip/index.html');
    }

    #[Test]
    public function itReturnsZeroForEmptyList(): void
    {
        $writer = new OutputWriter();
        $count = $writer->write([]);

        self::assertSame(0, $count);
    }

    private function createDocument(string $relativePath, string $outputPath, string $renderedContent): Document
    {
        return new Document(
            sourcePath: '/site/'.$relativePath,
            relativePath: $relativePath,
            frontMatter: [],
            rawContent: 'raw',
            contentType: 'md',
            title: pathinfo($relativePath, \PATHINFO_FILENAME),
            slug: pathinfo($relativePath, \PATHINFO_FILENAME),
            published: true,
            outputPath: $outputPath,
            renderedContent: $renderedContent,
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
