# Limb

A really simple static site generator. Less is more, buddy...

## Markdown usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$renderer = new \Limb\Markdown\MarkdownRenderer();
$html = $renderer->toHtml("# Hello\n\nThis is **markdown**.");

echo $html;
```

## Metadata usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$renderer = new \Limb\Markdown\MarkdownRenderer();
$limb = $renderer->parse("---\n" .
    "title: Hello\n" .
    "tags:\n" .
    "  - intro\n" .
    "  - demo\n" .
    "---\n\n" .
    "# Hello\n\nThis is **markdown**.");

if ($limb === null) {
    echo "Failed to parse markdown.";
    return;
}

var_dump($limb->metadata);
echo $limb->html;
```

## Testing

Run the test suite with:

```sh
vendor/bin/phpunit
```
