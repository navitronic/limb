# Limb — Build Plan

> **Limb**: Less Is More, Buddy.
> A Symfony-native static site generator inspired by Jekyll's conceptual model.

---

## Decisions Log

| #  | Decision             | Resolution                                     | Why                              |
|----|----------------------|------------------------------------------------|----------------------------------|
| 1  | Project name         | **Limb** ("Less Is More, Buddy")               | Repo name, final                 |
| 2  | PHP version          | **8.4**                                        | Docker-encapsulated; use latest  |
| 3  | Symfony version      | **7.4 LTS** (7.4.7+)                          | Current LTS, long support        |
| 4  | Template naming      | **`.html.twig` required**                      | No ambiguity in resolution       |
| 5  | Repo model           | **Tool image + mounted site**                  | Clean separation from day one    |
| 6  | Serve mode           | **Build then serve static files**              | PHP built-in server on `_site/`  |
| 7  | Collection config    | **Simple YAML** (`collections:` key)           | Don't over-engineer              |
| 8  | Plugin model         | **Symfony events only** (v1)                   | Native, testable                 |
| 9  | Config merge order   | Defaults → `_config.yml` → env vars → CLI      | Deterministic precedence         |
| 10 | Twig namespaces      | `@layouts/`, `@includes/`                      | Explicit template resolution     |
| 11 | Markdown parser      | **league/commonmark ^2.8**                     | Extensible, well-maintained      |
| 12 | Base Docker image    | **php:8.4-cli** (Debian Bookworm)              | Stable extension support         |
| 13 | Post permalink       | `/:year/:month/:day/:title/`                   | Jekyll convention                |
| 14 | Page permalink       | `/:title/`                                     | Pretty URLs                      |
| 15 | Development method   | **Test-Driven Development (TDD)**              | Tests written before code        |
| 16 | Code quality tooling | **PHP-CS-Fixer + PHPStan**                     | Symfony-native; strictest levels |

---

## Methodology: Test-Driven Development

All implementation work follows TDD. For every checkpoint:

1. **Write tests first** — define the expected behaviour before writing any implementation code
2. **Run tests, confirm they fail** — verify the tests are meaningful (red)
3. **Write the minimum implementation** to make the tests pass (green)
4. **Refactor** if needed while keeping tests green

This applies to unit tests, integration tests, and fixture-based tests alike. Test files are always created before their corresponding source files.

---

## Conventions

**Inside the container:**
- Limb application: `/app`
- User site mount: `/site`
- Output: `/site/_site` (configurable via `destination` in config or `--destination` CLI flag)

**User-facing site structure:**
```
my-site/
  _config.yml
  _posts/           # Blog posts (YYYY-MM-DD-slug.md)
  _pages/           # Standalone pages
  _layouts/         # Twig layouts (*.html.twig)
  _includes/        # Twig partials (*.html.twig)
  _data/            # YAML/JSON data files
  _drafts/          # Unpublished posts
  assets/           # Static files (CSS, JS, images)
  index.md          # Site root
```

**Limb application structure:**
```
src/
  Command/          # site:build, site:init, site:serve, site:clean, site:doctor
  Config/           # SiteConfig, ConfigLoader, ConfigMerger
  Content/          # ContentLocator, content classification
  FrontMatter/      # FrontMatterParser
  Model/            # Document, Page, Post, Collection, Layout, StaticAsset, Site, BuildResult
  Collection/       # CollectionBuilder
  Rendering/        # DocumentRenderer, TwigEnvironmentFactory, LayoutResolver
  Permalink/        # PermalinkGenerator, OutputPathResolver
  Output/           # OutputWriter
  Asset/            # AssetCopier
  Data/             # DataLoader
  Markdown/         # MarkdownRenderer (wraps league/commonmark)
  Pipeline/         # BuildRunner, stage orchestration
  Event/            # Build lifecycle events
```

---

## Checkpoints

### Checkpoint 1 — Symfony Skeleton + Docker

**Goal:** A working Symfony console app running inside Docker that prints "Limb" when invoked.

**Tasks:**

- [x] Create the Symfony skeleton:
  ```bash
  composer create-project symfony/skeleton:"7.4.*" .
  ```
- [x] Install core dependencies:
  ```bash
  composer require twig symfony/twig-bundle twig/extra-bundle league/commonmark
  composer require --dev symfony/test-pack
  ```
- [x] Create `Dockerfile` with multi-stage build:
  - **Base stage** (`php:8.4-cli`): install `intl`, `zip`, `opcache` extensions; copy Composer from `composer:2`
  - **Dev stage**: extends base, adds xdebug, runs `composer install`, sets `CMD ["php", "bin/console"]`
  - **Production stage**: multi-stage with optimised autoloader, no dev dependencies
- [x] Create `docker-compose.yml`:
  - Service `app` using dev stage
  - Mount current directory to `/app`
  - Mount placeholder for site at `/site`
  - Default command: `php bin/console`
- [x] Create a `site:build` command skeleton (`src/Command/SiteBuildCommand.php`):
  - Accepts `--source` (default: `/site`), `--destination`, `--config`, `--drafts`, `--future`, `--verbose`
  - For now: outputs "Limb build starting..." and exits 0
- [x] Create `.gitignore` for Symfony (vendor/, var/, .env.local, etc.)

**Verification:**
```bash
docker compose build
docker compose run --rm app php bin/console site:build
# Output: "Limb build starting..."
# Exit code: 0

docker compose run --rm app php bin/console list
# Shows site:build in command list
```

**Done when:** `site:build` command executes inside Docker and returns exit code 0.

---

### Checkpoint 2 — PHP Code Quality Tooling

**Goal:** PHP-CS-Fixer and PHPStan are installed, configured, and passing on the existing codebase. All subsequent code must satisfy both tools.

**Tasks:**

- [x] Install PHP-CS-Fixer:
  ```bash
  composer require --dev friendsofphp/php-cs-fixer
  ```
- [x] Create `.php-cs-fixer.dist.php` at the project root:
  - Use the `@Symfony` rule set as the base (Symfony's own coding standard)
  - Enable `@Symfony:risky` rules
  - Enable `declare_strict_types` fixer (enforce `declare(strict_types=1)` in every PHP file)
  - Enable `strict_param` fixer
  - Set finder to scan `src/` and `tests/`
  - Exclude `var/`, `vendor/`, `config/`
  - Example config:
    ```php
    <?php
    $finder = (new PhpCsFixer\Finder())
        ->in([__DIR__ . '/src', __DIR__ . '/tests'])
        ->exclude(['var', 'vendor']);

    return (new PhpCsFixer\Config())
        ->setRules([
            '@Symfony' => true,
            '@Symfony:risky' => true,
            'declare_strict_types' => true,
            'strict_param' => true,
            'array_syntax' => ['syntax' => 'short'],
            'ordered_imports' => ['sort_algorithm' => 'alpha'],
            'no_unused_imports' => true,
            'single_line_throw' => false,
        ])
        ->setFinder($finder)
        ->setRiskyAllowed(true);
    ```
- [x] Install PHPStan with Symfony extension:
  ```bash
  composer require --dev phpstan/phpstan phpstan/phpstan-symfony
  ```
- [x] Create `phpstan.neon` at the project root:
  - Set level to `max` (level 9 — strictest)
  - Scan `src/`
  - Include the Symfony extension config
  - Example config:
    ```neon
    parameters:
        level: max
        paths:
            - src
        symfony:
            containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
    includes:
        - vendor/phpstan/phpstan-symfony/extension.neon
    ```
- [x] Run PHP-CS-Fixer on existing code and fix any issues:
  ```bash
  vendor/bin/php-cs-fixer fix
  ```
- [x] Run PHPStan on existing code and fix any issues:
  ```bash
  vendor/bin/phpstan analyse
  ```
- [x] Add Composer scripts for convenience:
  ```json
  {
    "scripts": {
      "cs:check": "php-cs-fixer fix --dry-run --diff",
      "cs:fix": "php-cs-fixer fix",
      "stan": "phpstan analyse",
      "lint": ["@cs:check", "@stan"],
      "test": "phpunit"
    }
  }
  ```
- [x] Add `.php-cs-fixer.cache` to `.gitignore`

**Verification:**
```bash
docker compose run --rm app composer cs:check
# No fixable violations found — exit code 0

docker compose run --rm app composer stan
# No errors — exit code 0

docker compose run --rm app composer lint
# Both checks pass — exit code 0
```

**Done when:** Both PHP-CS-Fixer (`@Symfony` rules) and PHPStan (level max) pass cleanly. Composer scripts `cs:check`, `cs:fix`, `stan`, and `lint` are wired up.

**From this point forward:** All code written in subsequent checkpoints must pass `composer lint` before the checkpoint is considered complete.

---

### Checkpoint 3 — Configuration Loading

**Goal:** Limb reads `_config.yml` from the site directory and merges it with defaults into a typed `SiteConfig` object.

**Tasks:**

- [x] Write unit tests first:
  - `tests/Config/ConfigLoaderTest.php` — valid YAML, invalid YAML, missing file
  - `tests/Config/ConfigMergerTest.php` — merge precedence (defaults < config < env < CLI)
- [x] Run tests, confirm they fail
- [x] Create `src/Config/SiteConfig.php` — a value object holding:
  - `title` (string), `baseUrl` (string), `url` (string)
  - `source` (string), `destination` (string, default `_site`)
  - `layoutsDir` (string, default `_layouts`), `includesDir` (string, default `_includes`)
  - `dataDir` (string, default `_data`), `postsDir` (string, default `_posts`)
  - `collections` (array), `defaults` (array)
  - `permalink` (string, default `/:year/:month/:day/:title/`)
  - `timezone` (string, default `UTC`)
  - `exclude` (string[]), `include` (string[])
- [x] Create `src/Config/ConfigLoader.php` — service that:
  - Reads `_config.yml` from the source path
  - Returns parsed YAML array
  - Throws clear exception with file path if YAML is invalid
- [x] Create `src/Config/ConfigMerger.php` — service that:
  - Starts with hardcoded framework defaults
  - Merges `_config.yml` values on top
  - Applies environment variable overrides (`LIMB_TITLE`, `LIMB_BASE_URL`, etc.)
  - Applies CLI flag overrides
  - Returns a `SiteConfig` instance
- [x] Register services in `config/services.yaml`
- [x] Update `site:build` to load and merge config, output resolved config values in verbose mode
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
# Create a test site directory with _config.yml
mkdir -p /tmp/test-site
echo 'title: "Test Site"' > /tmp/test-site/_config.yml

docker compose run --rm -v /tmp/test-site:/site app php bin/console site:build --source=/site -v
# Verbose output shows: title = "Test Site", destination = "_site", etc.

docker compose run --rm app php bin/phpunit
# Config tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Config loads from YAML, merges correctly per precedence order, unit tests pass, and `composer lint` is clean.

---

### Checkpoint 4 — Content Discovery & Classification

**Goal:** Limb scans a site directory and classifies every file as content, layout, include, data, static asset, or excluded.

**Tasks:**

- [x] Create test fixture `tests/Fixtures/basic-site/`:
  ```
  _config.yml
  _layouts/default.html.twig
  _includes/header.html.twig
  _posts/2026-01-15-hello-world.md
  _data/navigation.yml
  assets/style.css
  index.md
  about.md
  ```
- [x] Write unit tests first:
  - `tests/Content/ContentLocatorTest.php` — use the fixture directory
  - Test: posts detected, layouts detected, static assets detected, excluded files skipped, `_site` ignored
- [x] Run tests, confirm they fail
- [x] Create `src/Content/ContentClassification.php` — enum representing file type
- [x] Create `src/Content/ScanResult.php` — holds categorised file lists
- [x] Create `src/Content/ContentLocator.php` — service that:
  - Uses Symfony Finder to recursively scan the source directory
  - Classifies files into categories:
    - **Layout**: files under `_layouts/` (`.html.twig`)
    - **Include**: files under `_includes/` (`.html.twig`)
    - **Data**: files under `_data/` (`.yml`, `.yaml`, `.json`)
    - **Post**: files under `_posts/` (`.md`, `.markdown`, `.html`)
    - **Draft**: files under `_drafts/` (`.md`, `.markdown`, `.html`)
    - **Page**: `.md`, `.markdown`, `.html` files with front matter in root or `_pages/`
    - **Static**: everything else not in an underscore directory and not excluded
  - Respects `exclude` and `include` config arrays
  - Ignores `_site/`, `_config.yml`, and dotfiles by default
- [x] Update `site:build` to run content discovery after config loading; in verbose mode, output counts: "Found X pages, Y posts, Z layouts, W includes, N static files"
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Content/
# All content discovery tests pass

docker compose run --rm -v /tmp/test-site:/site app php bin/console site:build --source=/site -v
# Shows file counts per category

docker compose run --rm app composer lint
# Clean
```

**Done when:** Scanner correctly classifies all file types, tests pass with fixture site, and `composer lint` is clean.

---

### Checkpoint 5 — Front Matter Parsing

**Goal:** Limb extracts YAML front matter from content files, returning structured metadata and raw body separately.

**Tasks:**

- [x] Write unit tests first (`tests/FrontMatter/FrontMatterParserTest.php`):
  - Valid front matter with various YAML types (strings, arrays, booleans, dates)
  - No front matter → empty metadata, full body
  - Invalid YAML → clear exception with context
  - Empty front matter (`---\n---`) → empty metadata, empty body
  - Front matter with `---` inside body content (only first block is front matter)
- [x] Run tests, confirm they fail
- [x] Create `src/FrontMatter/ParsedContent.php` — value object: `metadata` (array), `body` (string), `hasFrontMatter` (bool)
- [x] Create `src/FrontMatter/FrontMatterParser.php` — service that:
  - Detects `---` delimited front matter block at the start of a file
  - Extracts YAML between the delimiters
  - Parses YAML into an associative array
  - Returns a `ParsedContent` with `metadata` (array) and `body` (string)
  - Throws exception with file path and line number on invalid YAML
  - Returns empty metadata + full body if no front matter is present
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/FrontMatter/
# All tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Parser correctly splits front matter from body in all test cases. `composer lint` is clean.

---

### Checkpoint 6 — Document Model

**Goal:** A unified `Document` model represents all renderable content (pages, posts, collection items) with consistent attributes.

**Tasks:**

- [x] Write unit tests first:
  - `tests/Model/DocumentFactoryTest.php` — post filename parsing, page creation, front matter attribute mapping
  - Verify date extraction from `2026-03-10-my-post.md` → date=2026-03-10, slug=my-post
- [x] Run tests, confirm they fail
- [x] Create `src/Model/Document.php` — value object / entity:
  - `sourcePath` (string) — absolute path to source file
  - `relativePath` (string) — relative to site root
  - `frontMatter` (array) — parsed YAML metadata
  - `rawContent` (string) — body after front matter extraction
  - `contentType` (string) — `md` or `html`
  - `outputPath` (string) — resolved filesystem path for output
  - `url` (string) — URL for this document
  - `layoutName` (?string) — from front matter `layout` key
  - `published` (bool) — default true, false if `published: false` in front matter
  - `collection` (?string) — e.g. `posts`, or custom collection name
  - `renderedContent` (?string) — populated after rendering
  - `date` (?\DateTimeInterface) — from filename or front matter
  - `slug` (string) — from filename or front matter `slug` key
  - `title` (string) — from front matter `title` key
- [x] Create `src/Model/Site.php` — holds the full site model:
  - `config` (SiteConfig)
  - `pages` (Document[])
  - `posts` (Document[])
  - `collections` (array<string, Collection>)
  - `data` (array) — loaded from `_data/`
  - `staticAssets` (string[]) — relative paths of static files
- [x] Create `src/Model/Collection.php`:
  - `name` (string)
  - `documents` (Document[])
  - `permalink` (?string) — collection-level permalink pattern
  - `output` (bool) — whether to render collection documents
- [x] Create `src/Model/BuildResult.php`:
  - `pagesRendered` (int), `postsRendered` (int), `staticFilesCopied` (int)
  - `errors` (string[]), `warnings` (string[])
  - `elapsedTime` (float)
- [x] Create a `DocumentFactory` or builder method that takes a `ScanResult` file + `ParsedContent` and produces a `Document`:
  - Infer `date` and `slug` from post filenames (`YYYY-MM-DD-slug.md`)
  - Set `collection` based on source directory
  - Set `contentType` from file extension
  - Set `published` from front matter (default `true`)
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Model/
# All model tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Documents are constructed from scanned files with correct attributes. Post filename parsing works. `composer lint` is clean.

---

### Checkpoint 7 — Markdown Rendering

**Goal:** Limb converts Markdown content to HTML using league/commonmark.

**Tasks:**

- [x] Write unit tests first (`tests/Markdown/MarkdownRendererTest.php`):
  - Basic Markdown (headings, paragraphs, lists, links, code blocks)
  - Empty input → empty output
  - HTML passthrough (HTML in Markdown source should be preserved)
- [x] Run tests, confirm they fail
- [x] Create `src/Markdown/MarkdownRenderer.php` — service that:
  - Wraps `league/commonmark` `CommonMarkConverter` (or `MarkdownConverter` with `CommonMarkCoreExtension`)
  - Converts Markdown string to HTML string
  - Configurable (later) for extensions like tables, autolinks, etc.
  - Stateless — receives Markdown, returns HTML
- [x] Register as a Symfony service
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Markdown/
# All tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Markdown converts to correct HTML in all test cases. `composer lint` is clean.

---

### Checkpoint 8 — Data Loading

**Goal:** Limb loads YAML and JSON files from `_data/` into a `site.data` structure accessible in templates.

**Tasks:**

- [x] Add fixture data files to `tests/Fixtures/basic-site/_data/`
- [x] Write unit tests first (`tests/Data/DataLoaderTest.php`):
  - Single YAML file
  - Multiple files
  - Nested directories
  - JSON file
  - Invalid YAML → clear error
- [x] Run tests, confirm they fail
- [x] Create `src/Data/DataLoader.php` — service that:
  - Scans `_data/` directory
  - Parses `.yml`/`.yaml` files via Symfony YAML
  - Parses `.json` files via `json_decode`
  - Builds a nested associative array keyed by filename (without extension)
  - Supports subdirectories: `_data/authors/team.yml` → `site.data.authors.team`
  - Throws on invalid YAML/JSON with file path in error
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Data/
# All tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Data files load into a nested array structure matching their directory/file hierarchy. `composer lint` is clean.

---

### Checkpoint 9 — Permalink & Output Path Resolution

**Goal:** Each document gets a URL and a filesystem output path based on permalink patterns.

**Tasks:**

- [x] Write unit tests first:
  - `tests/Permalink/PermalinkGeneratorTest.php`:
    - Post with date → correct URL from pattern
    - Page with title → correct URL
    - Front matter permalink override
    - Custom collection permalink
    - Unknown token → error
  - `tests/Permalink/OutputPathResolverTest.php`:
    - Pretty URL → `/about/index.html`
    - File URL → `/feed.xml`
- [x] Run tests, confirm they fail
- [x] Create `src/Permalink/PermalinkGenerator.php` — service that:
  - Takes a `Document` and a permalink pattern string
  - Supports tokens: `:year`, `:month`, `:day`, `:title`, `:slug`, `:collection`
  - Resolves tokens from document attributes
  - Returns the URL string (e.g. `/2026/03/10/my-post/`)
  - Uses document-level `permalink` from front matter if present (overrides pattern)
- [x] Create `src/Permalink/OutputPathResolver.php` — service that:
  - Takes a URL and destination directory
  - Applies rules:
    - URL ending in `/` → `<dest>/<url>/index.html`
    - URL with file extension → `<dest>/<url>` as-is
  - Returns absolute filesystem path
- [x] Implement permalink pattern resolution order:
  - Front matter `permalink` (exact override) → use as-is
  - Collection-level `permalink` pattern → resolve tokens
  - Config-level `permalink` pattern → resolve tokens
  - Framework default (posts: `/:year/:month/:day/:title/`, pages: `/:title/`)
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Permalink/
# All tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Documents resolve to correct URLs and output paths per the precedence rules. `composer lint` is clean.

---

### Checkpoint 10 — Twig Rendering Pipeline

**Goal:** Documents are rendered through Twig layouts with full site context. This is where content becomes HTML pages.

**Tasks:**

- [x] Write unit tests first:
  - `tests/Rendering/DocumentRendererTest.php`:
    - Markdown document with layout → full HTML page
    - HTML document with layout → full HTML page
    - Document without layout → just rendered content
    - Missing layout → clear error
    - Site context available in templates
    - Layout chaining (post → default)
  - `tests/Collection/CollectionBuilderTest.php`:
    - Groups documents by collection
    - Sorts posts by date newest first
    - Returns empty array when no collections
    - Ignores documents with no collection
    - Sets output flag from config
    - Sets permalink from config
    - Creates Collection model instances
  - Use fixture layouts in `tests/Fixtures/basic-site/_layouts/`
- [x] Run tests, confirm they fail
- [x] Create `src/Rendering/TwigEnvironmentFactory.php` — service/factory that:
  - Creates a Twig Environment
  - Registers a filesystem loader with two namespaces:
    - `@layouts` → `<source>/_layouts/`
    - `@includes` → `<source>/_includes/`
  - Enables auto-escaping for HTML
- [x] Create `src/Rendering/DocumentRenderer.php` — service that:
  - Takes a `Document` and a `Site` model
  - If document is Markdown: converts body via `MarkdownRenderer` → HTML
  - If document is HTML: uses body as-is
  - Builds Twig render context:
    - `site` — config values + `posts`, `pages`, `collections`, `data`
    - `page` — document front matter + url, date, title, slug, collection
    - `content` — the rendered body HTML
  - If document has a layout: renders the layout template with context
  - If no layout: rendered content is the final output
  - Supports layout chaining: a layout can declare its own `layout` in its front matter (Jekyll-style recursive chain)
  - Layout resolution built-in: validates layout exists, throws clear error if missing
  - Returns rendered HTML string
- [x] Create `src/Collection/CollectionBuilder.php` — service that:
  - Groups documents by collection name
  - Sorts posts by date (newest first)
  - Creates `Collection` model instances
  - Marks collections with `output: true/false` per config
- [x] Wire rendering into `site:build`: after scanning, parsing, and permalink resolution, render each document and store `renderedContent` on the Document
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Rendering/ tests/Collection/
# All tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** A Markdown document with front matter renders into a complete HTML page wrapped in a Twig layout, with `site`, `page`, and `content` available in templates. `composer lint` is clean.

---

### Checkpoint 11 — Output Writing & Asset Copying

**Goal:** Rendered documents are written to the destination directory. Static assets are copied.

**Tasks:**

- [x] Write unit tests first:
  - `tests/Output/OutputWriterTest.php` — writes files, creates directories, detects duplicates, skips null rendered content
  - `tests/Asset/AssetCopierTest.php` — copies files, preserves structure, skips missing
- [x] Run tests, confirm they fail
- [x] Create `src/Output/OutputWriter.php` — service that:
  - Takes a list of rendered Documents (with `outputPath` and `renderedContent`)
  - Creates necessary directories
  - Writes each document's `renderedContent` to its `outputPath`
  - Detects duplicate output paths → error with both source files listed
  - Returns count of files written
- [x] Create `src/Asset/AssetCopier.php` — service that:
  - Takes the list of static asset absolute paths from `ScanResult`
  - Copies each file from source to destination, preserving relative directory structure
  - Skips missing source files gracefully
  - Returns count of files copied
- [x] Create `src/Command/SiteCleanCommand.php`:
  - Deletes the destination directory (`_site` by default)
  - Accepts `--source` and `--destination` options
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Output/ tests/Asset/
# All tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Documents write to correct paths and static files copy with directory structure preserved. `composer lint` is clean.

---

### Checkpoint 12 — Build Pipeline Integration

**Goal:** Wire all stages together into `BuildRunner`. The `site:build` command performs a complete build from source to output.

**Tasks:**

- [x] Write integration test first:
  - `tests/Integration/BuildRunnerTest.php`:
    - Uses `tests/Fixtures/basic-site/` fixture
    - Runs `BuildRunner` against it
    - Asserts `_site/index.html` exists and contains expected content
    - Asserts `_site/assets/style.css` exists (static asset copied)
    - Asserts post output exists at correct permalink path
    - Asserts `BuildResult` counts are correct
- [x] Run tests, confirm they fail
- [x] Create `src/Pipeline/BuildRunner.php` — the orchestrator service:
  - Executes the full pipeline in order:
    1. Load configuration (`ConfigLoader` + `ConfigMerger`)
    2. Scan site files (`ContentLocator`)
    3. Parse front matter for all content files (`FrontMatterParser`)
    4. Load data files (`DataLoader`)
    5. Create Document models (`DocumentFactory`)
    6. Build collections (`CollectionBuilder`)
    7. Compute URLs and output paths (`PermalinkGenerator` + `OutputPathResolver`)
    8. Render all documents (`DocumentRenderer`)
    9. Copy static assets (`AssetCopier`)
    10. Write rendered output (`OutputWriter`)
    11. Build and return `BuildResult`
  - Collects errors and warnings throughout
  - Measures elapsed time
  - Returns `BuildResult`
- [x] Create `src/Event/` lifecycle events (dispatched via Symfony EventDispatcher):
  - `SiteLoadedEvent` — after config + scan complete, before rendering
  - `BeforeRenderEvent` — before document rendering begins
  - `AfterRenderEvent` — after all documents rendered
  - `BuildCompleteEvent` — after output written, carries `BuildResult`
- [x] Update `src/Command/SiteBuildCommand.php`:
  - Inject `BuildRunner`
  - Pass CLI options through to config (--source, --destination, --config, --drafts, --future)
  - Output build report: pages rendered, posts rendered, static files copied, elapsed time
  - On errors: output each error, exit code 1
  - On warnings: output each warning, still exit 0
  - Verbose mode: list every file written
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
# Unit + integration tests
docker compose run --rm app php bin/phpunit

# End-to-end via CLI
mkdir -p /tmp/test-site/_layouts /tmp/test-site/_posts /tmp/test-site/assets

cat > /tmp/test-site/_config.yml << 'EOF'
title: "My Blog"
url: "https://example.com"
EOF

cat > /tmp/test-site/_layouts/default.html.twig << 'EOF'
<!DOCTYPE html>
<html>
<head><title>{{ page.title }} | {{ site.title }}</title></head>
<body>{{ content|raw }}</body>
</html>
EOF

cat > /tmp/test-site/index.md << 'EOF'
---
title: Home
layout: default
---
# Welcome to my site
EOF

cat > /tmp/test-site/_posts/2026-01-15-hello-world.md << 'EOF'
---
title: Hello World
layout: default
---
This is my first post.
EOF

echo "body { color: #333; }" > /tmp/test-site/assets/style.css

docker compose run --rm -v /tmp/test-site:/site app php bin/console site:build --source=/site
# Output: "Build complete: 1 page, 1 post, 1 static file in 0.XXs"

ls /tmp/test-site/_site/
# index.html, assets/style.css, 2026/01/15/hello-world/index.html

cat /tmp/test-site/_site/index.html
# Contains: <title>Home | My Blog</title> and <h1>Welcome to my site</h1>

docker compose run --rm app composer lint
# Clean
```

**Done when:** `site:build` produces a complete `_site/` directory from a source site with pages, posts, layouts, and static assets. All tests pass. `composer lint` is clean.

---

### Checkpoint 13 — site:init Command

**Goal:** `site:init` scaffolds a new site directory with example content so users can get started immediately.

**Tasks:**

- [x] Write test first:
  - `tests/Command/SiteInitCommandTest.php` — creates site in temp dir, asserts all files exist, asserts no overwrite
- [x] Run tests, confirm they fail
- [x] Store scaffold templates in `resources/scaffold/` within the Limb app
- [x] Create `src/Command/SiteInitCommand.php`:
  - Accepts a `path` argument (required) — where to create the site
  - Creates the directory structure:
    ```
    <path>/
      _config.yml        # Example config with title, url
      _layouts/
        default.html.twig  # Minimal HTML5 layout
        post.html.twig     # Extends default, adds date/title
      _includes/
        header.html.twig   # Simple nav
        footer.html.twig   # Simple footer
      _posts/
        YYYY-MM-DD-welcome.md  # Example post (today's date)
      _data/
        navigation.yml     # Example nav data
      assets/
        css/
          style.css        # Minimal CSS
      index.md              # Homepage
      about.md              # About page
    ```
  - Refuses to overwrite if directory already contains `_config.yml`
  - Outputs what was created
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/console site:init /site
# Creates scaffold

docker compose run --rm app php bin/console site:build --source=/site
# Builds successfully from the scaffold

docker compose run --rm app composer lint
# Clean
```

**Done when:** `site:init` creates a buildable site scaffold that `site:build` can process without errors. `composer lint` is clean.

---

### Checkpoint 14 — site:serve Command

**Goal:** `site:serve` builds the site and starts a local development server.

**Tasks:**

- [x] Create `src/Command/SiteServeCommand.php`:
  - Accepts `--source`, `--host` (default `0.0.0.0`), `--port` (default `4000`)
  - Runs `BuildRunner` to build the site
  - Starts PHP's built-in web server on the `_site/` output directory:
    ```
    php -S <host>:<port> -t <destination>
    ```
  - Uses `Process` component to manage the server process
  - Outputs the URL where the site is accessible
  - Handles SIGINT for clean shutdown
- [x] Update `docker-compose.yml`:
  - Add port mapping for the serve command (e.g. `4000:4000`)
  - Add a `serve` service profile or document usage:
    ```bash
    docker compose run --rm -p 4000:4000 app php bin/console site:serve --source=/site
    ```
- [x] Manual test (no automated test for server lifecycle):
  - Init a site, build it, serve it, open in browser
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm -p 4000:4000 -v /tmp/test-site:/site app php bin/console site:serve --source=/site
# Output: "Serving at http://0.0.0.0:4000"
# Browser: http://localhost:4000 shows the built site

docker compose run --rm app composer lint
# Clean
```

**Done when:** `site:serve` builds and serves the site. Pages are accessible in a browser. `composer lint` is clean.

---

### Checkpoint 15 — site:doctor Command

**Goal:** `site:doctor` validates a site's configuration and structure, reporting problems before build.

**Tasks:**

- [x] Write tests first:
  - `tests/Command/SiteDoctorCommandTest.php` — fixture with known issues (missing layout, bad filename), assert correct diagnostics
- [x] Run tests, confirm they fail
- [x] Create `src/Command/SiteDoctorCommand.php`:
  - Accepts `--source`
  - Runs checks:
    - `_config.yml` exists and is valid YAML
    - All layouts referenced by documents exist in `_layouts/`
    - All includes referenced by templates exist in `_includes/`
    - No duplicate output paths detected
    - Post filenames follow `YYYY-MM-DD-slug.md` convention
    - Permalink patterns contain only known tokens
  - Outputs OK / WARNING / ERROR for each check
  - Exit code 0 if no errors (warnings OK), exit code 1 if errors
- [x] Run tests, confirm they pass
- [x] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm -v /tmp/test-site:/site app php bin/console site:doctor --source=/site
# Output: all checks pass (or specific warnings)

docker compose run --rm app composer lint
# Clean
```

**Done when:** `site:doctor` reports config and structural issues clearly. Tests pass. `composer lint` is clean.

---

### Checkpoint 16 — Collections Support

**Goal:** Custom collections (beyond posts) work: configured in `_config.yml`, documents loaded, rendered if `output: true`.

**Tasks:**

- [ ] Add `tests/Fixtures/collections-site/` fixture with `_docs/` directory
- [ ] Write tests first:
  - `tests/Collection/CollectionBuilderTest.php` — custom collection loaded, sorted
  - Integration test: collection documents render at correct URLs
- [ ] Run tests, confirm they fail
- [ ] Update `ConfigLoader` to parse `collections` config:
  ```yaml
  collections:
    docs:
      output: true
      permalink: /docs/:title/
    team:
      output: true
      permalink: /team/:title/
  ```
- [ ] Update `ContentLocator` to detect `_<collection_name>/` directories for configured collections and classify files as collection documents
- [ ] Update `CollectionBuilder` to create `Collection` instances for each configured collection and sort documents (by date if available, then by title)
- [ ] Update `DocumentRenderer` template context: `site.collections.<name>` available in Twig, each collection document's `page.collection` set correctly
- [ ] Update `PermalinkGenerator` to use collection-level permalink patterns
- [ ] Run tests, confirm they pass
- [ ] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Collection/
# All tests pass

# Manual: create a site with _docs/ collection, build, verify output

docker compose run --rm app composer lint
# Clean
```

**Done when:** Custom collections render at their configured permalink paths. Documents are accessible via `site.collections.X` in templates. `composer lint` is clean.

---

### Checkpoint 17 — Twig Extensions & Template Polish

**Goal:** Add project-specific Twig functions/filters that make templates practical.

**Tasks:**

- [ ] Write unit tests first for each filter and function
- [ ] Run tests, confirm they fail
- [ ] Create `src/Rendering/LimbTwigExtension.php` (Symfony-registered Twig extension):
  - **Filters:**
    - `date_to_string` — format a date for display (e.g. `page.date|date_to_string` → "10 Mar 2026")
    - `slugify` — convert string to URL slug
    - `markdownify` — render inline Markdown to HTML
    - `xml_escape` — escape content for XML/RSS feeds
  - **Functions:**
    - `asset_url(path)` — prepend `site.baseUrl` to an asset path
    - `absolute_url(path)` — prepend `site.url` + `site.baseUrl` to a path
    - `collection(name)` — shorthand to get a collection's documents
- [ ] Register the extension as a tagged Symfony service
- [ ] Run tests, confirm they pass
- [ ] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Rendering/
# Twig extension tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** All Twig filters and functions work and are tested. Templates can use them. `composer lint` is clean.

---

### Checkpoint 18 — Error Handling Hardening

**Goal:** All error cases produce actionable messages with file paths and context.

**Tasks:**

- [ ] Write tests first for each error path
- [ ] Run tests, confirm they fail
- [ ] Review and harden error messages across all services:
  - `ConfigLoader`: "Invalid YAML in /site/_config.yml at line 15: \<parse error\>"
  - `FrontMatterParser`: "Invalid front matter in /site/_posts/2026-01-15-hello.md: \<parse error\>"
  - `LayoutResolver`: "Layout 'post' not found. Referenced by /site/index.md. Expected at /site/_layouts/post.html.twig"
  - `Twig include errors`: "Include 'sidebar.html.twig' not found. Referenced in layout 'default'. Expected at /site/_includes/sidebar.html.twig"
  - `OutputWriter`: "Duplicate output path: _site/about/index.html claimed by both /site/about.md and /site/_pages/about.md"
  - `PermalinkGenerator`: "Unknown permalink token ':author' in pattern '/:author/:title/'. Valid tokens: :year, :month, :day, :title, :slug, :collection"
- [ ] Create custom exception classes where useful:
  - `ConfigException`
  - `FrontMatterException`
  - `RenderException`
  - `OutputException`
- [ ] Update `site:build` to catch exceptions and format them for CLI output (no raw stack traces in normal mode, stack traces in `-vvv`)
- [ ] Run tests, confirm they pass
- [ ] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit
# All tests pass, including error-case tests

# Manual: introduce a bad _config.yml, run build, verify error message is helpful

docker compose run --rm app composer lint
# Clean
```

**Done when:** Every known error case produces a message that tells the user exactly what's wrong and where. `composer lint` is clean.

---

### Checkpoint 19 — Full Integration Test Suite

**Goal:** Comprehensive fixture-based tests prove the entire pipeline works correctly.

**Tasks:**

- [ ] Create fixture sites in `tests/Fixtures/`:
  - `basic-pages/` — index.md, about.md, simple layout
  - `blog-posts/` — multiple posts, post layout, date-based permalinks
  - `collections/` — custom collection with config
  - `data-files/` — `_data/` with YAML and JSON, template using `site.data`
  - `nested-layouts/` — layout that extends another layout
  - `permalink-overrides/` — documents with front matter `permalink` overrides
  - `drafts/` — drafts included/excluded based on --drafts flag
- [ ] Write integration tests for each fixture:
  - Run `BuildRunner` against fixture
  - Assert specific output files exist
  - Assert output HTML contains expected content (use string matching, not DOM parsing)
  - Assert correct file counts in `BuildResult`
- [ ] Write a "golden file" test:
  - One complete fixture site with expected output committed to the repo
  - Test compares actual `_site/` output against expected output file-by-file
- [ ] Run all tests, confirm they pass
- [ ] Run `composer lint` — confirm clean

**Verification:**
```bash
docker compose run --rm app php bin/phpunit tests/Integration/
# All integration tests pass

docker compose run --rm app composer lint
# Clean
```

**Done when:** Full test suite covers all major features with fixture sites. Golden file test confirms byte-accurate output. `composer lint` is clean.

---

## MVP Acceptance Test

The MVP is complete when the following end-to-end scenario works:

```bash
# 1. Build the Docker image
docker compose build

# 2. Scaffold a new site
docker compose run --rm app php bin/console site:init /site

# 3. Verify the scaffold is healthy
docker compose run --rm -v ./my-site:/site app php bin/console site:doctor --source=/site
# All checks pass

# 4. Build the site
docker compose run --rm -v ./my-site:/site app php bin/console site:build --source=/site
# Output: "Build complete: X pages, Y posts, Z static files in 0.XXs"

# 5. Verify output
test -f my-site/_site/index.html               # Homepage exists
test -f my-site/_site/about/index.html          # About page exists
test -f my-site/_site/assets/css/style.css      # Static assets copied
grep -q "My Blog" my-site/_site/index.html      # Site title rendered in layout

# Find the post output (date-based path from scaffold)
find my-site/_site -name "index.html" -path "*/welcome/*"  # Post exists at permalink path

# 6. Serve and verify
docker compose run --rm -p 4000:4000 -v ./my-site:/site app php bin/console site:serve --source=/site
# Browser: http://localhost:4000 shows the homepage
# Browser: clicking nav links works
# Ctrl+C stops the server

# 7. Clean
docker compose run --rm -v ./my-site:/site app php bin/console site:clean --source=/site
test ! -d my-site/_site                          # Output directory removed

# 8. All automated tests pass
docker compose run --rm app php bin/phpunit
# All tests green

# 9. Code quality passes
docker compose run --rm app composer lint
# PHP-CS-Fixer and PHPStan both clean
```

**The MVP is shipped when all 9 steps above succeed.**
