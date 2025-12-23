<?php

declare(strict_types=1);

namespace Limb\Tests\Container;

use League\Container\Container;
use Limb\Container\LimbContainerFactory;
use Limb\Console\Command\RenderCommand;
use Limb\Markdown\FrontMatterParser;
use Limb\Markdown\MarkdownHtmlRenderer;
use Limb\Markdown\MarkdownRenderer;
use Limb\Markdown\MetadataResolver;
use PHPUnit\Framework\TestCase;

final class LimbContainerFactoryTest extends TestCase
{
    public function testCreateBuildsContainerWithMarkdownServices(): void
    {
        $container = LimbContainerFactory::create();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertInstanceOf(FrontMatterParser::class, $container->get(FrontMatterParser::class));
        $this->assertInstanceOf(MetadataResolver::class, $container->get(MetadataResolver::class));
        $this->assertInstanceOf(MarkdownHtmlRenderer::class, $container->get(MarkdownHtmlRenderer::class));
        $this->assertInstanceOf(MarkdownRenderer::class, $container->get(MarkdownRenderer::class));
        $this->assertInstanceOf(RenderCommand::class, $container->get(RenderCommand::class));
    }
}
