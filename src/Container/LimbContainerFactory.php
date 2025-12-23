<?php

declare(strict_types=1);

namespace Limb\Container;

use League\Container\Container;
use Limb\Console\Command\RenderCommand;
use Limb\Markdown\FrontMatterParser;
use Limb\Markdown\MarkdownHtmlRenderer;
use Limb\Markdown\MarkdownRenderer;
use Limb\Markdown\MetadataResolver;

final class LimbContainerFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): Container
    {
        $container = new Container();

        $container->add(FrontMatterParser::class);
        $container->add(MetadataResolver::class);
        $container->add(MarkdownHtmlRenderer::class)->addArgument($config);

        $container->add(MarkdownRenderer::class)->addArguments([
            FrontMatterParser::class,
            MarkdownHtmlRenderer::class,
            MetadataResolver::class,
        ]);

        $container->add(RenderCommand::class)->addArgument(MarkdownRenderer::class);

        return $container;
    }
}
