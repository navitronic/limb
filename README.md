# Limb

A really simple static site generator. Less is more, buddy...

## CLI

The project ships a minimal CLI entry point:

```sh
./bin/limb list
./bin/limb render content/post.md > output.html
```

If installed via Composer, you can also run:

```sh
vendor/bin/limb render content/post.md > output.html
```

## Markdown usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$container = \Limb\Container\LimbContainerFactory::create();
$renderer = $container->get(\Limb\Markdown\MarkdownRenderer::class);
$html = $renderer->toHtml("# Hello\n\nThis is **markdown**.");

echo $html;
```

## Metadata usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$container = \Limb\Container\LimbContainerFactory::create();
$renderer = $container->get(\Limb\Markdown\MarkdownRenderer::class);
$limb = $renderer->parse("---\n" .
    "title: Hello\n" .
    "tags:\n" .
    "  - intro\n" .
    "  - demo\n" .
    "draft: true\n" .
    "---\n\n" .
    "# Hello\n\nThis is **markdown**.");

if ($limb === null) {
    echo "Failed to parse markdown.";
    return;
}

var_dump($limb->metadata);

$resolver = $container->get(\Limb\Markdown\MetadataResolver::class);
$resolved = $resolver->resolve($limb->metadata, $limb->content, null);

var_dump($resolved->extra);
echo $limb->html;
```

## Testing

Run the test suite with:

```sh
vendor/bin/phpunit
```

## Publishing (Packagist)

This package is intended for Packagist distribution.

1. Create the package on Packagist and point it at this GitHub repository.
2. Add the Packagist webhook to the GitHub repo (Packagist provides the URL).
3. Tag releases and push tags (e.g., `git tag v0.1.0 && git push --tags`).

Packagist will auto-update on new tags once the webhook is configured.
