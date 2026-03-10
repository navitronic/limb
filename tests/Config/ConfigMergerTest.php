<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\ConfigMerger;
use App\Config\SiteConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigMergerTest extends TestCase
{
    #[Test]
    public function itReturnsDefaultsWhenNoConfigProvided(): void
    {
        $merger = new ConfigMerger();
        $config = $merger->merge([]);

        self::assertInstanceOf(SiteConfig::class, $config);
        self::assertSame('', $config->title);
        self::assertSame('', $config->baseUrl);
        self::assertSame('_site', $config->destination);
        self::assertSame('_layouts', $config->layoutsDir);
        self::assertSame('_includes', $config->includesDir);
        self::assertSame('_data', $config->dataDir);
        self::assertSame('_posts', $config->postsDir);
        self::assertSame('/:year/:month/:day/:title/', $config->permalink);
        self::assertSame('UTC', $config->timezone);
        self::assertSame([], $config->exclude);
        self::assertSame([], $config->include);
        self::assertSame([], $config->collections);
        self::assertSame([], $config->defaults);
    }

    #[Test]
    public function itMergesYamlConfigOverDefaults(): void
    {
        $merger = new ConfigMerger();
        $config = $merger->merge([
            'title' => 'My Site',
            'baseUrl' => 'https://example.com',
            'destination' => 'output',
            'permalink' => '/:title/',
        ]);

        self::assertSame('My Site', $config->title);
        self::assertSame('https://example.com', $config->baseUrl);
        self::assertSame('output', $config->destination);
        self::assertSame('/:title/', $config->permalink);
        // Unset values remain defaults
        self::assertSame('_layouts', $config->layoutsDir);
    }

    #[Test]
    public function itAppliesEnvironmentVariableOverrides(): void
    {
        $_ENV['LIMB_TITLE'] = 'Env Title';
        $_ENV['LIMB_BASE_URL'] = 'https://env.example.com';

        try {
            $merger = new ConfigMerger();
            $config = $merger->merge(['title' => 'YAML Title']);

            self::assertSame('Env Title', $config->title);
            self::assertSame('https://env.example.com', $config->baseUrl);
        } finally {
            unset($_ENV['LIMB_TITLE'], $_ENV['LIMB_BASE_URL']);
        }
    }

    #[Test]
    public function itAppliesCliOverrides(): void
    {
        $merger = new ConfigMerger();
        $config = $merger->merge(
            ['title' => 'YAML Title', 'destination' => 'yaml_dest'],
            ['destination' => 'cli_dest'],
        );

        self::assertSame('YAML Title', $config->title);
        self::assertSame('cli_dest', $config->destination);
    }

    #[Test]
    public function itFollowsMergePrecedence(): void
    {
        // defaults < config < env < CLI
        $_ENV['LIMB_TITLE'] = 'Env Title';

        try {
            $merger = new ConfigMerger();
            $config = $merger->merge(
                ['title' => 'YAML Title', 'destination' => 'yaml_dest'],
                ['destination' => 'cli_dest'],
            );

            // ENV overrides YAML for title
            self::assertSame('Env Title', $config->title);
            // CLI overrides YAML for destination
            self::assertSame('cli_dest', $config->destination);
        } finally {
            unset($_ENV['LIMB_TITLE']);
        }
    }

    #[Test]
    public function itSetsSourceFromParameter(): void
    {
        $merger = new ConfigMerger();
        $config = $merger->merge([], [], '/custom/source');

        self::assertSame('/custom/source', $config->source);
    }

    #[Test]
    public function itMergesCollectionsAndDefaults(): void
    {
        $merger = new ConfigMerger();
        $config = $merger->merge([
            'collections' => ['projects' => ['output' => true]],
            'defaults' => [
                ['scope' => ['type' => 'posts'], 'values' => ['layout' => 'post']],
            ],
        ]);

        self::assertSame(['projects' => ['output' => true]], $config->collections);
        self::assertCount(1, $config->defaults);
    }

    #[Test]
    public function itHandlesExcludeAndIncludeArrays(): void
    {
        $merger = new ConfigMerger();
        $config = $merger->merge([
            'exclude' => ['node_modules', '.git'],
            'include' => ['.htaccess'],
        ]);

        self::assertSame(['node_modules', '.git'], $config->exclude);
        self::assertSame(['.htaccess'], $config->include);
    }
}
