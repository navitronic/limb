# Limb

**Less Is More, Buddy** — a Symfony-native static site generator.

Limb takes a directory of Markdown files, Twig templates, and static assets and produces a complete static website. It runs entirely inside Docker — your site is a mounted volume, and Limb is the tool image that builds it.

## Features

- Markdown content with YAML front matter
- Twig templating with layout chaining
- Blog-aware (date-based posts with permalink patterns)
- Custom collections (projects, recipes, anything)
- Data files (YAML/JSON accessible in templates)
- Includes and partial templates
- Asset copying
- Built-in development server
- Site health checker (`site:doctor`)
- Scaffold generator for new sites

## Prerequisites

- [Docker](https://www.docker.com/) with Compose v2

That's it. PHP, Composer, and all dependencies are encapsulated in the Docker image.

## Quick start

```bash
# Build the Docker image
docker compose build

# Scaffold a new site
docker compose run --rm app php bin/console site:init /site

# Validate the site structure
docker compose run --rm app php bin/console site:doctor --source=/site

# Build the site
docker compose run --rm app php bin/console site:build --source=/site

# Start the development server (http://localhost:4000)
docker compose run --rm --service-ports app php bin/console site:serve --source=/site
```

## Site structure

After running `site:init`, your site directory looks like this:

```
my-site/
├── _config.yml          # Site configuration
├── _data/
│   └── navigation.yml   # Data files (YAML or JSON)
├── _includes/
│   ├── header.html.twig # Partial templates
│   └── footer.html.twig
├── _layouts/
│   ├── default.html.twig  # Base layout
│   └── post.html.twig     # Post layout (chains to default)
├── _posts/
│   └── 2026-03-10-welcome.md  # Blog posts (date-prefixed)
├── assets/
│   └── css/
│       └── style.css    # Static assets (copied as-is)
├── about.md             # Pages
└── index.md
```

## Configuration

Site configuration lives in `_config.yml`:

```yaml
title: "My Site"
url: "http://localhost:4000"
description: "A site built with Limb"
permalink: "/:year/:month/:day/:title/"
```

### All options

| Key          | Default                      | Description                            |
| ------------ | ---------------------------- | -------------------------------------- |
| `title`      | `""`                         | Site title, available as `site.title`  |
| `url`        | `""`                         | Production URL of the site             |
| `baseUrl`    | `""`                         | Base path if site lives in a subdirectory |
| `destination`| `_site`                      | Output directory (relative to source)  |
| `permalink`  | `/:year/:month/:day/:title/` | Default permalink pattern for posts    |
| `timezone`   | `UTC`                        | Timezone for date operations           |
| `exclude`    | `[]`                         | Glob patterns to exclude from build    |
| `include`    | `[]`                         | Glob patterns to force-include         |
| `collections`| `{}`                         | Custom collection definitions          |
| `defaults`   | `[]`                         | Default front matter values by path    |

### Configuration precedence

Values are merged in this order (last wins):

1. Built-in defaults
2. `_config.yml`
3. Environment variables
4. CLI flags

### Environment variables

| Variable           | Overrides    |
| ------------------ | ------------ |
| `LIMB_TITLE`       | `title`      |
| `LIMB_BASE_URL`    | `baseUrl`    |
| `LIMB_URL`         | `url`        |
| `LIMB_DESTINATION` | `destination`|
| `LIMB_PERMALINK`   | `permalink`  |
| `LIMB_TIMEZONE`    | `timezone`   |

## Commands

### `site:init <path>`

Scaffold a new site with starter templates, layouts, a sample post, and configuration.

```bash
docker compose run --rm app php bin/console site:init /site
```

### `site:build`

Build the static site from source to destination.

```bash
docker compose run --rm app php bin/console site:build --source=/site
```

| Flag              | Short | Default  | Description                  |
| ----------------- | ----- | -------- | ---------------------------- |
| `--source`        | `-s`  | `/site`  | Source directory              |
| `--destination`   | `-d`  | (config) | Override destination         |
| `--config`        | `-c`  | (auto)   | Path to config file          |
| `--drafts`        |       |          | Include draft posts          |
| `--future`        |       |          | Include future-dated posts   |

### `site:serve`

Build the site and start a development server using PHP's built-in web server.

```bash
docker compose run --rm --service-ports app php bin/console site:serve --source=/site
```

| Flag              | Short | Default    | Description              |
| ----------------- | ----- | ---------- | ------------------------ |
| `--source`        | `-s`  | `/site`    | Source directory          |
| `--host`          |       | `0.0.0.0`  | Host to bind to          |
| `--port`          | `-p`  | `4000`     | Port to serve on         |
| `--destination`   | `-d`  | (config)   | Override destination     |
| `--config`        | `-c`  | (auto)     | Path to config file      |
| `--drafts`        |       |            | Include draft posts      |

### `site:doctor`

Validate site configuration, layouts, includes, posts, and permalink patterns. Useful for debugging build issues.

```bash
docker compose run --rm app php bin/console site:doctor --source=/site
```

| Flag       | Short | Default | Description     |
| ---------- | ----- | ------- | --------------- |
| `--source` | `-s`  | `/site` | Source directory |

### `site:clean`

Remove the built output directory.

```bash
docker compose run --rm app php bin/console site:clean --source=/site
```

| Flag            | Short | Default  | Description          |
| --------------- | ----- | -------- | -------------------- |
| `--source`      | `-s`  | `/site`  | Source directory      |
| `--destination` | `-d`  | (config) | Override destination |

## Content

### Front matter

Every content file starts with YAML front matter between `---` delimiters:

```markdown
---
title: "My Page"
layout: default
permalink: /custom-url/
date: 2026-03-10
draft: true
custom_key: any value
---

Your markdown content here.
```

| Key         | Description                                        |
| ----------- | -------------------------------------------------- |
| `title`     | Page title                                         |
| `layout`    | Layout template to use (without `.html.twig`)      |
| `permalink` | Override the generated URL for this page           |
| `date`      | Publication date (required for posts by filename)  |
| `draft`     | If `true`, excluded unless `--drafts` is passed    |

Any additional keys are available as `page.<key>` in templates.

### Posts

Posts live in `_posts/` and must be named with a date prefix:

```
_posts/2026-03-10-my-post-title.md
```

The date and slug are extracted from the filename and used to generate the permalink.

### Pages

Any `.md` or `.html` file outside `_posts/` and special directories is treated as a page. Pages use their file path as the URL (e.g., `about.md` becomes `/about/`).

## Templating

Limb uses [Twig](https://twig.symfony.com/) for templates. All template files must use the `.html.twig` extension.

### Layouts

Layouts live in `_layouts/` and wrap your content. A layout receives the rendered content via the `{{ content|raw }}` variable:

```twig
{# _layouts/default.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>{{ page.title }} | {{ site.title }}</title>
</head>
<body>
    {{ content|raw }}
</body>
</html>
```

### Layout chaining

Layouts can declare a parent layout via YAML front matter (not Twig `{% extends %}`). Limb renders the inner layout first, then passes the result as `content` to the parent:

```twig
{# _layouts/post.html.twig #}
---
layout: default
---
<article>
    <h1>{{ page.title }}</h1>
    {{ content|raw }}
</article>
```

This renders the post content inside the `post` layout, then wraps the result in the `default` layout.

### Includes

Partial templates live in `_includes/` and are included with:

```twig
{% include '@includes/header.html.twig' %}
```

### Template variables

#### `site`

| Variable           | Description                                |
| ------------------ | ------------------------------------------ |
| `site.title`       | Site title from config                     |
| `site.url`         | Site URL from config                       |
| `site.baseUrl`     | Base URL from config                       |
| `site.posts`       | Array of all published posts               |
| `site.pages`       | Array of all pages                         |
| `site.collections` | Map of collection name to array of documents |
| `site.data`        | Data from files in `_data/`                |

#### `page`

| Variable         | Description                                  |
| ---------------- | -------------------------------------------- |
| `page.title`     | Page title from front matter                 |
| `page.url`       | Generated URL/permalink                      |
| `page.date`      | Publication date (`DateTimeImmutable`)        |
| `page.slug`      | URL slug                                     |
| `page.collection`| Collection name (if part of a collection)    |
| `page.content`   | Raw markdown content                         |
| `page.<key>`     | Any front matter key                         |

#### `content`

The rendered content of the current page/post, to be placed in layouts with `{{ content|raw }}`.

### Custom Twig filters

| Filter           | Description                              | Example                           |
| ---------------- | ---------------------------------------- | --------------------------------- |
| `date_to_string` | Format a date as `dd Mon YYYY`           | `{{ page.date\|date_to_string }}` |
| `slugify`        | Convert text to a URL-safe slug          | `{{ "Hello World"\|slugify }}`    |
| `markdownify`    | Render markdown string to HTML           | `{{ text\|markdownify }}`         |
| `xml_escape`     | Escape text for XML/RSS output           | `{{ title\|xml_escape }}`         |

### Custom Twig functions

| Function                | Description                                   | Example                               |
| ----------------------- | --------------------------------------------- | ------------------------------------- |
| `asset_url(path)`       | Prefix a path with the base URL               | `{{ asset_url('css/style.css') }}`    |
| `absolute_url(path)`    | Generate a full absolute URL                  | `{{ absolute_url(page.url) }}`        |

## Permalinks

The default permalink pattern is configured in `_config.yml`:

```yaml
permalink: "/:year/:month/:day/:title/"
```

### Available tokens

| Token     | Description                         | Example    |
| --------- | ----------------------------------- | ---------- |
| `:year`   | 4-digit year from post date         | `2026`     |
| `:month`  | 2-digit month from post date        | `03`       |
| `:day`    | 2-digit day from post date          | `10`       |
| `:title`  | Slug from filename                  | `my-post`  |
| `:slug`   | Same as `:title`                    | `my-post`  |

Individual pages and posts can override their permalink in front matter:

```yaml
---
permalink: /custom/path/
---
```

## Collections

Define custom collections in `_config.yml`:

```yaml
collections:
  projects:
    output: true
    permalink: "/projects/:title/"
  recipes:
    output: true
```

Each collection corresponds to a directory named `_<collection>/` (e.g., `_projects/`). Documents in a collection are available in templates as `site.collections.<name>`:

```twig
{% for project in site.collections.projects %}
    <a href="{{ project.url }}">{{ project.title }}</a>
{% endfor %}
```

## Data files

YAML and JSON files in `_data/` are loaded and available in templates via `site.data.<filename>` (without extension):

```yaml
# _data/navigation.yml
- title: Home
  url: /
- title: About
  url: /about/
```

```twig
{% for item in site.data.navigation %}
    <a href="{{ item.url }}">{{ item.title }}</a>
{% endfor %}
```

## Development

### Running locally (without Docker)

Requires PHP 8.4+ and Composer.

```bash
composer install
php bin/console site:build --source=./test-site
```

### Tests

```bash
# Via Docker
docker compose run --rm app php bin/phpunit

# Locally
composer test
```

148 tests, 372 assertions. The test suite includes unit tests for every component and integration tests with fixture sites covering collections, nested layouts, data files, permalink overrides, drafts, and a golden file comparison test.

### Linting

```bash
# Full lint (code style + static analysis)
composer lint

# Individual tools
composer cs:check    # PHP-CS-Fixer (dry run)
composer cs:fix      # PHP-CS-Fixer (fix)
composer stan        # PHPStan (level max)
```

### Architecture

```
src/
├── Asset/          # Static file copying
├── Collection/     # Collection building
├── Command/        # Console commands (site:build, site:serve, etc.)
├── Config/         # Configuration loading and merging
├── Content/        # File discovery and classification
├── Data/           # YAML/JSON data file loading
├── Event/          # Build lifecycle events
├── Exception/      # Typed exceptions
├── FrontMatter/    # YAML front matter parsing
├── Markdown/       # CommonMark rendering
├── Model/          # Document, Site, Collection, BuildResult
├── Output/         # File writing
├── Permalink/      # URL generation and output path resolution
├── Pipeline/       # BuildRunner orchestrator
└── Rendering/      # Twig environment, document rendering, extensions
```

The build pipeline (`BuildRunner`) orchestrates the full process:

1. Load configuration
2. Scan source directory
3. Parse front matter and build document models
4. Load data files
5. Build collections
6. Render documents through Twig layout chain
7. Write output files
8. Copy static assets

Lifecycle events (`SiteLoadedEvent`, `BeforeRenderEvent`, `AfterRenderEvent`, `BuildCompleteEvent`) are dispatched at each stage for extensibility.

## License

Proprietary.
