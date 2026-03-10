<?php

declare(strict_types=1);

namespace App\Rendering;

use League\CommonMark\CommonMarkConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class LimbTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $siteUrl = '',
    ) {
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('date_to_string', $this->dateToString(...)),
            new TwigFilter('slugify', $this->slugify(...)),
            new TwigFilter('markdownify', $this->markdownify(...), ['is_safe' => ['html']]),
            new TwigFilter('xml_escape', $this->xmlEscape(...)),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_url', $this->assetUrl(...)),
            new TwigFunction('absolute_url', $this->absoluteUrl(...)),
        ];
    }

    public function dateToString(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '';
        }

        return $date->format('d M Y');
    }

    public function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = (string) preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = (string) preg_replace('/[\s-]+/', '-', $text);

        return trim($text, '-');
    }

    public function markdownify(string $text): string
    {
        $converter = new CommonMarkConverter();

        return trim($converter->convert($text)->getContent());
    }

    public function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }

    public function assetUrl(string $path): string
    {
        $base = rtrim($this->baseUrl, '/');

        return $base.'/'.$path;
    }

    public function absoluteUrl(string $path): string
    {
        $url = rtrim($this->siteUrl, '/');
        $base = rtrim($this->baseUrl, '/');

        return $url.$base.$path;
    }
}
