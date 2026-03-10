<?php

declare(strict_types=1);

namespace App\Tests\Permalink;

use App\Permalink\OutputPathResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutputPathResolverTest extends TestCase
{
    private OutputPathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OutputPathResolver();
    }

    #[Test]
    public function itResolvesPrettyUrlToIndexHtml(): void
    {
        $path = $this->resolver->resolve('/about/', '/output');

        self::assertSame('/output/about/index.html', $path);
    }

    #[Test]
    public function itResolvesFileUrlAsIs(): void
    {
        $path = $this->resolver->resolve('/feed.xml', '/output');

        self::assertSame('/output/feed.xml', $path);
    }

    #[Test]
    public function itResolvesRootUrlToIndexHtml(): void
    {
        $path = $this->resolver->resolve('/', '/output');

        self::assertSame('/output/index.html', $path);
    }

    #[Test]
    public function itResolvesNestedPrettyUrl(): void
    {
        $path = $this->resolver->resolve('/2026/01/15/hello-world/', '/output');

        self::assertSame('/output/2026/01/15/hello-world/index.html', $path);
    }

    #[Test]
    public function itResolvesHtmlExtensionAsIs(): void
    {
        $path = $this->resolver->resolve('/page.html', '/output');

        self::assertSame('/output/page.html', $path);
    }
}
