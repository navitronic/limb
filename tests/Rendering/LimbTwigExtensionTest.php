<?php

declare(strict_types=1);

namespace App\Tests\Rendering;

use App\Rendering\LimbTwigExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class LimbTwigExtensionTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([]), [
            'autoescape' => false,
        ]);
        $this->twig->addExtension(new LimbTwigExtension('', 'http://example.com'));
    }

    #[Test]
    public function itFormatsDateToString(): void
    {
        $template = $this->twig->createTemplate('{{ date|date_to_string }}');
        $result = $template->render(['date' => new \DateTimeImmutable('2026-03-10')]);

        self::assertSame('10 Mar 2026', $result);
    }

    #[Test]
    public function itReturnsEmptyStringForNullDate(): void
    {
        $template = $this->twig->createTemplate('{{ date|date_to_string }}');
        $result = $template->render(['date' => null]);

        self::assertSame('', $result);
    }

    #[Test]
    public function itSlugifiesStrings(): void
    {
        $template = $this->twig->createTemplate('{{ text|slugify }}');

        self::assertSame('hello-world', $template->render(['text' => 'Hello World']));
        self::assertSame('foo-bar-baz', $template->render(['text' => ' Foo  Bar  Baz ']));
        self::assertSame('special-chars', $template->render(['text' => 'Special & Chars!']));
    }

    #[Test]
    public function itRendersInlineMarkdown(): void
    {
        $template = $this->twig->createTemplate('{{ text|markdownify }}');
        $result = $template->render(['text' => '**bold** and *italic*']);

        self::assertStringContainsString('<strong>bold</strong>', $result);
        self::assertStringContainsString('<em>italic</em>', $result);
    }

    #[Test]
    public function itEscapesXml(): void
    {
        $template = $this->twig->createTemplate('{{ text|xml_escape }}');
        $result = $template->render(['text' => '<p>Hello & "world"</p>']);

        self::assertSame('&lt;p&gt;Hello &amp; &quot;world&quot;&lt;/p&gt;', $result);
    }

    #[Test]
    public function itGeneratesAssetUrl(): void
    {
        $twig = new Environment(new ArrayLoader([]), ['autoescape' => false]);
        $twig->addExtension(new LimbTwigExtension('/blog', 'http://example.com'));

        $template = $twig->createTemplate('{{ asset_url("assets/style.css") }}');
        $result = $template->render([]);

        self::assertSame('/blog/assets/style.css', $result);
    }

    #[Test]
    public function itGeneratesAssetUrlWithEmptyBaseUrl(): void
    {
        $template = $this->twig->createTemplate('{{ asset_url("assets/style.css") }}');
        $result = $template->render([]);

        self::assertSame('/assets/style.css', $result);
    }

    #[Test]
    public function itGeneratesAbsoluteUrl(): void
    {
        $twig = new Environment(new ArrayLoader([]), ['autoescape' => false]);
        $twig->addExtension(new LimbTwigExtension('/blog', 'http://example.com'));

        $template = $twig->createTemplate('{{ absolute_url("/about/") }}');
        $result = $template->render([]);

        self::assertSame('http://example.com/blog/about/', $result);
    }

    #[Test]
    public function itGeneratesAbsoluteUrlWithEmptyBase(): void
    {
        $template = $this->twig->createTemplate('{{ absolute_url("/about/") }}');
        $result = $template->render([]);

        self::assertSame('http://example.com/about/', $result);
    }
}
