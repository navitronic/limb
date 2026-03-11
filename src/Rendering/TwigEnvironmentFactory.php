<?php

declare(strict_types=1);

namespace Limb\Rendering;

use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

final class TwigEnvironmentFactory
{
    /**
     * Create a Twig Environment configured for site rendering.
     *
     * Sets up filesystem loaders for layouts and includes directories,
     * and registers project-specific Twig filters and functions.
     */
    public function create(string $sourceDir, string $baseUrl = '', string $siteUrl = ''): Environment
    {
        $layoutsDir = $sourceDir.'/_layouts';
        $includesDir = $sourceDir.'/_includes';

        $loader = new FilesystemLoader();

        if (is_dir($layoutsDir)) {
            $loader->addPath($layoutsDir, 'layouts');
        }

        if (is_dir($includesDir)) {
            $loader->addPath($includesDir, 'includes');
        }

        $chainLoader = new ChainLoader([$loader]);

        $env = new Environment($chainLoader, [
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);

        $env->addExtension(new LimbTwigExtension($baseUrl, $siteUrl));

        return $env;
    }
}
