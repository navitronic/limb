<?php

declare(strict_types=1);

namespace App\Config;

final class ConfigMerger
{
    private const array ENV_MAP = [
        'LIMB_TITLE' => 'title',
        'LIMB_BASE_URL' => 'baseUrl',
        'LIMB_URL' => 'url',
        'LIMB_DESTINATION' => 'destination',
        'LIMB_PERMALINK' => 'permalink',
        'LIMB_TIMEZONE' => 'timezone',
    ];

    /**
     * Merge configuration with precedence: defaults < YAML config < env vars < CLI overrides.
     *
     * @param array<string, mixed> $yamlConfig   Parsed YAML config values
     * @param array<string, mixed> $cliOverrides CLI flag overrides
     */
    public function merge(array $yamlConfig, array $cliOverrides = [], ?string $source = null): SiteConfig
    {
        $defaults = $this->getDefaults();

        // Layer 1: defaults < YAML config
        $merged = array_merge($defaults, $yamlConfig);

        // Layer 2: env var overrides
        foreach (self::ENV_MAP as $envVar => $configKey) {
            $value = $_ENV[$envVar] ?? null;
            if (null !== $value && '' !== $value) {
                $merged[$configKey] = $value;
            }
        }

        // Layer 3: CLI overrides
        foreach ($cliOverrides as $key => $value) {
            if (null !== $value) {
                $merged[$key] = $value;
            }
        }

        if (null !== $source) {
            $merged['source'] = $source;
        }

        return new SiteConfig(
            title: $this->getString($merged, 'title', ''),
            baseUrl: $this->getString($merged, 'baseUrl', ''),
            url: $this->getString($merged, 'url', ''),
            source: $this->getString($merged, 'source', '/site'),
            destination: $this->getString($merged, 'destination', '_site'),
            layoutsDir: $this->getString($merged, 'layoutsDir', '_layouts'),
            includesDir: $this->getString($merged, 'includesDir', '_includes'),
            dataDir: $this->getString($merged, 'dataDir', '_data'),
            postsDir: $this->getString($merged, 'postsDir', '_posts'),
            permalink: $this->getString($merged, 'permalink', '/:year/:month/:day/:title/'),
            timezone: $this->getString($merged, 'timezone', 'UTC'),
            exclude: $this->getStringArray($merged, 'exclude'),
            include: $this->getStringArray($merged, 'include'),
            collections: $this->getAssocArray($merged, 'collections'),
            defaults: $this->getListArray($merged, 'defaults'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            'title' => '',
            'baseUrl' => '',
            'url' => '',
            'source' => '/site',
            'destination' => '_site',
            'layoutsDir' => '_layouts',
            'includesDir' => '_includes',
            'dataDir' => '_data',
            'postsDir' => '_posts',
            'permalink' => '/:year/:month/:day/:title/',
            'timezone' => 'UTC',
            'exclude' => [],
            'include' => [],
            'collections' => [],
            'defaults' => [],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return \is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return string[]
     */
    private function getStringArray(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function getAssocArray(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (!\is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = $value;

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, mixed>
     */
    private function getListArray(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (!\is_array($value)) {
            return [];
        }

        return array_values($value);
    }
}
