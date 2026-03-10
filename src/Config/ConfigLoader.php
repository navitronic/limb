<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    /**
     * Load configuration from a YAML file.
     *
     * @return array<string, mixed>
     */
    public function load(string $sourceDir, ?string $configPath = null): array
    {
        $path = $configPath ?? $sourceDir.'/_config.yml';

        if (null !== $configPath && !file_exists($path)) {
            throw new \RuntimeException(\sprintf('Config file not found: %s', $path));
        }

        if (!file_exists($path)) {
            return [];
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new \RuntimeException(\sprintf('Failed to parse config file "%s": %s', $path, $e->getMessage()), 0, $e);
        }

        if (!\is_array($parsed)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = $parsed;

        return $result;
    }
}
