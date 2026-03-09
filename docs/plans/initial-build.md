# Jekyll-in-PHP Plan

## Goal

Build a static site generator inspired by Jekyll, implemented in PHP, using:

* **Symfony (full framework)** as the application foundation
* **Symfony Console** for CLI commands
* **Twig** as the template engine in place of Liquid
* **Docker** as the standard runtime and development environment

This project does **not** need Liquid compatibility. The aim is to reproduce the core *conceptual model* of Jekyll in a PHP/Symfony idiom rather than create a drop-in replacement.

---

## 1. Project scope

### Core features to replicate

The first version should support the core Jekyll workflow:

* A project directory containing content, layouts, includes, assets, and config
* Markdown pages and posts with front matter
* Data-driven rendering via templates
* Layout inheritance
* Collections
* Permalink generation
* Static asset copying
* Site build into an output directory
* Local development server
* Incremental improvements toward plugins, drafts, pagination, and watch mode

### Explicit non-goals for v1

To keep the first implementation tractable, avoid these initially:

* Liquid syntax or Liquid tag compatibility
* Full Jekyll plugin compatibility
* GitHub Pages compatibility guarantees
* Complex safe-mode emulation
* Highly dynamic plugin sandboxing
* Exact parity with every Jekyll edge case

The initial target should be: **a clean, Symfony-native static site generator that feels like Jekyll**.

---

## 2. Product definition

### Working name

Use a temporary internal name until the architecture stabilises.

Examples:

* `StaticForge`
* `Twigyll`
* `SiteKernel`
* `Pagenode Static`

### User experience target

A user should be able to:

```bash
docker run --rm -v "$PWD":/app project build
```

or, in a local containerised dev setup:

```bash
docker compose run --rm app php bin/console site:build
```

and get:

* rendered HTML in `_site/`
* copied assets
* generated pages for posts, collections, and standalone pages

---

## 3. High-level architecture

Use the **full Symfony framework** as the basis for the application rather than only standalone components.

### Why full Symfony

This gives the project:

* Dependency injection and service configuration
* Console commands
* Event dispatcher
* Filesystem utilities
* Config handling
* HTTP kernel support for local preview/dev server integration
* Environment management
* Cache facilities
* Better extensibility for future plugins and pipelines

### Core architectural layers

The app should be separated into these concerns:

1. **Project discovery**
2. **Configuration loading and merging**
3. **Content loading**
4. **Front matter parsing**
5. **Site model construction**
6. **Rendering pipeline**
7. **Output writing**
8. **Static asset copying**
9. **Development server / watch loop**
10. **Extension hooks**

This should be designed as a pipeline rather than a monolithic command.

---

## 4. Suggested directory structure

### Symfony application structure

Use a normal Symfony app layout, with custom domain code under `src/`.

```text
project/
  bin/
    console
  config/
    packages/
    services.yaml
  public/
  src/
    Command/
    Config/
    Content/
    FrontMatter/
    Model/
    Collection/
    Rendering/
    Routing/
    Output/
    Asset/
    Markdown/
    Site/
    Event/
    Plugin/
    Dev/
  templates/
  var/
  vendor/
  docker/
  Dockerfile
  docker-compose.yml
```

### User site directory structure

The generator should operate on a site project structure inspired by Jekyll:

```text
site/
  _config.yml
  _posts/
  _pages/
  _layouts/
  _includes/
  _data/
  _drafts/
  _plugins/
  assets/
  index.md
  about.md
```

This site may be the working directory mounted into the container, while Symfony runs the generator logic.

A later decision is whether the Symfony app and the user site live:

* in the same repository, or
* with Symfony packaged as a reusable tool image that processes an external mounted site

For flexibility, prefer **tool image + mounted site**.

---

## 5. Core domain model

Define explicit PHP objects rather than passing arrays everywhere.

### Recommended models

* `SiteConfig`
* `Site`
* `Page`
* `Post`
* `Document`
* `Collection`
* `Layout`
* `IncludeReference`
* `StaticAsset`
* `DataRepository`
* `RenderContext`
* `BuildResult`

### Shared document attributes

A common `Document` abstraction should hold:

* source path
* relative path
* front matter
* raw body content
* parsed content type
* output path
* URL
* layout name
* published state
* collection membership
* rendered content

This makes pages, posts, and collection documents uniform.

---

## 6. Configuration design

### Primary config source

Support a `_config.yml` file at the site root.

### Configuration categories

At minimum:

* `title`
* `base_url`
* `url`
* `source`
* `destination`
* `layouts_dir`
* `includes_dir`
* `data_dir`
* `posts_dir`
* `collections`
* `defaults`
* `permalink`
* `markdown`
* `timezone`
* `exclude`
* `include`

### Merge order

Define a deterministic precedence order:

1. framework defaults
2. application config defaults
3. site `_config.yml`
4. environment variables
5. CLI flags

This should be formalised early because it affects reproducibility.

### Implementation notes

Use Symfony Config and YAML components where helpful, but keep the final resolved config mapped into strongly typed objects.

---

## 7. Front matter handling

### Required support

Support YAML front matter in Markdown and HTML-ish content files.

Example:

```yaml
---
title: About
layout: page
permalink: /about/
---
```

### Processing approach

Create a dedicated front matter parser service that:

1. detects front matter blocks
2. extracts the YAML section
3. parses it into structured metadata
4. returns metadata + remaining body content

### Design rule

Do not mix front matter parsing into the Markdown renderer. It should be a separate stage so other content formats can reuse it.

---

## 8. Content loading strategy

### Supported content types for v1

* Markdown files (`.md`, `.markdown`)
* HTML files with front matter
* Static files copied as-is
* YAML/JSON data files under `_data`

### Content sources

Load from:

* root pages
  n- `_posts`
* `_pages`
* custom collections
* special directories such as `_drafts`

### Post naming

For Jekyll-style posts, support filenames like:

```text
2026-03-10-my-post.md
```

The loader should infer:

* date
* slug
* collection = `posts`

### Content discovery service

Create a `ContentLocator` or `SiteScanner` service responsible for:

* recursive directory scanning
* ignoring excluded files
* classifying content vs static assets
* routing files into the right loaders

---

## 9. Markdown pipeline

Use a modern PHP Markdown parser rather than implementing one.

### Recommendation

Use:

* `league/commonmark`

### Why

It is flexible, well-supported, and extensible for future custom syntax.

### Pipeline

1. raw source file
2. front matter extraction
3. Markdown conversion to HTML
4. insertion into Twig layout rendering

Do not tightly couple Markdown conversion to Twig. Markdown should produce HTML content that becomes part of the document render context.

---

## 10. Twig templating model

Twig replaces Liquid entirely.

### Template directories

Use site-level directories:

* `_layouts` for page layouts
* `_includes` for reusable partials

### Mapping strategy

Decide how file names map to Twig templates.

For example:

* `_layouts/default.html.twig`
* `_layouts/post.html.twig`
* `_includes/header.html.twig`

To reduce ambiguity, standardise on Twig file extensions internally even if you allow shorthand source naming.

### Rendering context

Provide Twig with a Jekyll-like site context, such as:

* `site`
* `page`
* `content`
* `paginator` later
* `collections`
* `posts`
* `data`

Example Twig layout:

```twig
<!DOCTYPE html>
<html>
  <head>
    <title>{{ page.title }} | {{ site.title }}</title>
  </head>
  <body>
    {% include 'header.html.twig' %}
    {{ content|raw }}
  </body>
</html>
```

### Custom Twig extensions

Add project-specific Twig functions and filters for things like:

* URL generation
* date formatting
* collection lookup
* asset path helpers
* excerpt generation

These should be added through standard Symfony/Twig extension services.

---

## 11. Layout and include resolution

### Layout flow

A document should:

1. render its body content
2. resolve its declared layout
3. inject the body into the layout as `content`
4. optionally allow nested layouts later

### Include flow

Includes should resolve relative to `_includes` via Twig loader configuration.

### Recommendation

Configure Twig namespaces for clarity, e.g.:

* `@layouts`
* `@includes`

This avoids brittle path strings and keeps template resolution explicit.

---

## 12. Site data model

Jekyll sites rely on global site data. Mirror that concept.

### `_data` support

Load YAML and JSON files from `_data` into a `site.data` structure.

Examples:

* `_data/navigation.yml`
* `_data/authors.yml`

Accessible in Twig as:

```twig
{% for item in site.data.navigation %}
  <a href="{{ item.url }}">{{ item.title }}</a>
{% endfor %}
```

### Collections

Collections should be queryable in templates, e.g.:

* `site.posts`
* `site.collections.docs`

The collection system should sort, filter, and expose metadata consistently.

---

## 13. URL and permalink generation

### Requirements

Support:

* default pretty URLs
* front matter permalink overrides
* collection-based permalink templates
* post URLs derived from date and slug

### Suggested permalink token support

Support a practical subset first:

* `:year`
* `:month`
* `:day`
* `:title`
* `:slug`
* `:collection`

### Output rules

Examples:

* `/about/` → `_site/about/index.html`
* `/blog/my-post/` → `_site/blog/my-post/index.html`
* `/feed.xml` → `_site/feed.xml`

This logic should live in a dedicated `PermalinkGenerator` or `OutputPathResolver` service.

---

## 14. Build pipeline design

Create a formal build pipeline with clear stages.

### Suggested stages

1. load configuration
2. scan site files
3. classify inputs
4. parse front matter
5. load data files
6. build collections
7. compute URLs/output paths
8. render documents
9. copy static assets
10. write output
11. emit build report

### Important rule

Each stage should be independently testable and should not assume direct filesystem side effects unless that stage owns them.

---

## 15. CLI design with Symfony Console

Since the app is based on full Symfony, commands should be standard Symfony console commands.

### Initial command set

* `site:init` — scaffold a new site
* `site:build` — build the static site
* `site:serve` — local preview server
* `site:clean` — remove generated output
* `site:doctor` — validate config and site structure

### Recommended command options

For `site:build`:

* `--source=`
* `--destination=`
* `--config=`
* `--drafts`
* `--future`
* `--incremental`
* `--verbose`

For `site:serve`:

* `--host=`
* `--port=`
* `--watch`
* `--poll`

### CLI design principle

Commands should orchestrate services, not contain build logic directly.

---

## 16. Development server strategy

There are two reasonable options.

### Option A: Symfony local HTTP kernel route

Use Symfony itself to serve the generated site or preview responses.

Pros:

* consistent with the framework
* easier future extension
* can support richer preview/debug features

Cons:

* more moving parts
* more framework overhead

### Option B: build then serve static output

Have `site:serve`:

1. build the site
2. watch for changes
3. serve `_site/` via a lightweight PHP server mechanism

Pros:

* closest to static-site behavior
* simpler mental model

Cons:

* less integrated with Symfony runtime features

### Recommendation

Start with **Option B**. It is simpler and matches user expectations for a static generator.

---

## 17. Watch mode and incremental build

This should not block v1, but the architecture should allow it.

### Watch mode goals

* rebuild on file changes
* detect which files changed
* re-render only affected outputs where possible

### Early approach

For the first pass:

* implement full rebuild on change
* add file hashing/cache manifests later

### Future extension

Store a build manifest in `var/cache` or a site-local cache to support incremental rebuild decisions.

---

## 18. Static asset handling

### v1 requirement

Copy non-rendered files directly into the destination directory.

### Rules

* honour include/exclude config
* preserve relative paths
* do not copy internal source-only files such as layouts/includes/config unless intended

### Possible later enhancements

* asset fingerprinting
* minification hooks
* image pipelines
* Sass/JS integration

Those are add-ons, not core generator responsibilities for the first milestone.

---

## 19. Plugin and extension design

Do not attempt Jekyll plugin compatibility. Build a Symfony-native extension model.

### Recommended mechanism

Use Symfony events and tagged services.

Examples:

* `SiteDiscoveredEvent`
* `BeforeRenderEvent`
* `AfterRenderEvent`
* `BeforeWriteEvent`
* `BuildFinishedEvent`

### Benefits

* native to Symfony architecture
* easier dependency injection
* safer than arbitrary executable plugins
* easier testability

### v1 plan

Implement extension points, even if only internal listeners use them initially.

---

## 20. Docker strategy

A containerised workflow is a required part of the system.

### Goals

* consistent PHP runtime
* zero host PHP requirement
* simple site mounting
* reproducible build and serve workflow

### Deliverables

Create:

* `Dockerfile`
* `docker-compose.yml`
* optional `Makefile`

### Base image

Use an official PHP CLI image such as:

* `php:8.3-cli`
* or `php:8.3-cli-alpine` if image size matters and extensions remain manageable

### Dockerfile responsibilities

* install system dependencies
* install Composer
* install PHP extensions needed by Symfony/Twig/YAML/intl if required
* set working directory
* copy app source
* install Composer dependencies
* define default command

### Example container workflow

The container should support commands like:

```bash
docker compose run --rm app php bin/console site:build
```

and:

```bash
docker compose up
```

for local serve mode.

### Volume strategy

Mount the user site into the container, for example:

* `/workspace/app` for the Symfony tool
* `/workspace/site` for the content project

Or package them together in a single repo during the early phase.

### Recommendation

For initial simplicity, keep the Symfony app and example site in one repository. Once stable, separate the generator image from the site content.

---

## 21. Testing strategy

This project will need strong automated coverage because static generators are mostly pipeline and edge-case logic.

### Test layers

#### Unit tests

Cover:

* front matter parsing
* permalink generation
* config merging
* collection sorting
* output path resolution
* include/layout resolution

#### Integration tests

Given a fixture site:

* run the build command
* assert generated files exist
* assert output HTML matches expectations

#### End-to-end tests

Using Docker:

* build the container
* run generator commands in the container
* assert mounted site output

### Fixture strategy

Maintain multiple small fixture sites representing:

* basic pages
* blog posts
* collections
* data files
* drafts
* nested layouts
* permalink overrides

---

## 22. Error handling and diagnostics

The tool should be strict and observable.

### Required diagnostics

Surface clear errors for:

* invalid YAML in `_config.yml`
* invalid front matter
* missing layout
* missing include
* duplicate output paths
* invalid permalink pattern
* unsupported collection config

### CLI output

Provide:

* summary counts of rendered pages/posts/assets
* elapsed time
* warnings list
* optional verbose file-by-file output

A `site:doctor` command should validate common setup mistakes before build.

---

## 23. Suggested implementation phases

### Phase 1: Foundation

* create Symfony app
* add Dockerfile and Docker Compose
* wire Composer dependencies
* create `site:build` command skeleton
* implement config loading
* implement filesystem scanning

### Phase 2: Basic rendering

* add front matter parsing
* add Markdown conversion
* add Twig rendering
* support layouts and includes
* write generated files to `_site`

### Phase 3: Content model

* add posts
* add collections
* add `_data`
* add permalink generation
* add sorting and template context exposure

### Phase 4: Usability

* add `site:init`
* add `site:serve`
* add `site:clean`
* add `site:doctor`
* improve diagnostics

### Phase 5: Extension and performance

* add events/hooks
* add watch mode
* add incremental build support
* add cache manifest

---

## 24. Recommended Composer dependencies

A likely starting set:

```json
{
  "require": {
    "php": "^8.3",
    "symfony/framework-bundle": "^7.0",
    "symfony/console": "^7.0",
    "symfony/yaml": "^7.0",
    "symfony/finder": "^7.0",
    "symfony/filesystem": "^7.0",
    "symfony/string": "^7.0",
    "twig/twig": "^3.0",
    "twig/extra-bundle": "^3.0",
    "league/commonmark": "^2.0"
  },
  "require-dev": {
    "symfony/test-pack": "^1.0",
    "phpunit/phpunit": "^11.0"
  }
}
```

Exact versions can be adjusted to current PHP/Symfony support policy.

---

## 25. Proposed internal service breakdown

A practical first service map:

* `SiteConfigLoader`
* `ConfigMerger`
* `SiteScanner`
* `FrontMatterParser`
* `MarkdownRenderer`
* `TwigEnvironmentFactory`
* `LayoutResolver`
* `IncludeResolver`
* `DataLoader`
* `CollectionBuilder`
* `PermalinkGenerator`
* `OutputPathResolver`
* `DocumentRenderer`
* `AssetCopier`
* `BuildRunner`
* `BuildReportFactory`

The `BuildRunner` should coordinate the build process and return a `BuildResult`.

---

## 26. Example MVP workflow

### User flow

1. create a site directory
2. add `_config.yml`
3. add `_layouts/default.html.twig`
4. add `index.md`
5. run build command in Docker
6. inspect `_site/index.html`

### Minimum viable feature set

The MVP should support:

* one config file
* Markdown pages
* Twig layouts
* includes
* static asset copy
* posts in `_posts`
* `_data` YAML
* `_site` output

That is enough to prove the architecture before tackling advanced parity.

---

## 27. Key design decisions to lock early

These should be decided before too much code is written:

1. **Template naming convention**

   * Require `.html.twig` everywhere, or allow looser names?

2. **Site/tool repo model**

   * Single repository initially, separate tool/site later?

3. **Serve mode behavior**

   * Serve generated static files only, or integrate live preview deeper with Symfony?

4. **Collection config shape**

   * How closely to mirror Jekyll config vs using a cleaner PHP-native design?

5. **Plugin model boundaries**

   * Events only, or user-defined PHP extensions later?

6. **Incremental build strategy**

   * Simple file timestamps vs content hashing vs dependency graph?

---

## 28. Risks and pitfalls

### 1. Over-chasing Jekyll compatibility

Trying to mimic every Jekyll edge case will slow the project and distort the PHP design.

### 2. Too much array-based state

If site/document/context data are passed around as arbitrary arrays, the codebase will become fragile quickly.

### 3. Templating ambiguity

If Twig path resolution is not strict, layouts/includes will become confusing.

### 4. Build pipeline leakage

Mixing scanning, parsing, rendering, and writing into one step will make testing difficult.

### 5. Docker ergonomics

A containerised setup that is awkward with mounted volumes, permissions, or cache directories will hurt adoption.

---

## 29. Recommended first milestone

The first meaningful milestone should be:

### Milestone: “Build a basic blog site”

Deliver a working prototype that can:

* read `_config.yml`
* parse Markdown files with YAML front matter
* render with Twig layouts and includes
* build posts from `_posts`
* expose `site`, `page`, and `content` to templates
* copy assets
* output to `_site`
* run fully inside Docker

This milestone is small enough to finish and large enough to validate the architecture.

---

## 30. Concrete next steps

1. Scaffold a full Symfony application.
2. Add Dockerfile and `docker-compose.yml`.
3. Install Twig, CommonMark, YAML, Finder, and Filesystem dependencies.
4. Create `site:build` command.
5. Implement `_config.yml` loading and merge rules.
6. Implement site scanning and content classification.
7. Implement front matter parsing.
8. Implement Markdown-to-HTML conversion.
9. Implement Twig layout rendering.
10. Implement output path resolution and file writing.
11. Add fixture-based integration tests.
12. Create a sample blog site to verify the workflow.

---

## 31. Final recommendation

The best path is to treat this as a **Symfony application that happens to be a static site generator**, not as a direct Jekyll port.

That means:

* preserve Jekyll’s user-facing ideas where they are valuable
* replace Liquid with Twig cleanly rather than emulating compatibility
* lean on Symfony’s service container, console, events, and config systems
* make Docker the default execution environment from the beginning

This will produce a more maintainable PHP codebase and a clearer long-term extension story than trying to mirror Ruby/Jekyll internals too closely.
