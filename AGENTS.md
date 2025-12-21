# Repository Guidelines

## Project Structure & Module Organization

- `src/` contains the PHP 8.4 source code (autoloaded via Composer `Limb\\` namespace). Key entry points live here, such as `MarkdownRenderer` and `Limb`.
- `composer.json`/`composer.lock` define dependencies; `vendor/` is Composer-managed and should not be edited manually.
- `mago.toml` configures formatting and static analysis rules for the repo.
- `README.md` documents usage examples for Markdown rendering and metadata parsing.

## Build, Test, and Development Commands

- `composer install`: install PHP dependencies into `vendor/`.
- `composer update <package>`: add/update a dependency and refresh `composer.lock`.
- `vendor/bin/mago analyze`: run static analysis (required before PRs).
- `vendor/bin/mago format`: apply the configured formatter (4 spaces, 120 char lines).

There is no build step or local server yet; this is a library-style codebase.

## Coding Style & Naming Conventions

- PHP 8.4, 4-space indentation, spaces (no tabs), 120-character line width (see `mago.toml`).
- Favor clear method names and small helpers (e.g., `resolveSlug`, `parseDateValue`).
- Use `Limb\\` namespace for all library classes under `src/`.

## Testing Guidelines

- No test framework is set up yet. If you add tests, document the command here and keep them in `tests/`.
- When changing parsing logic, include at least one minimal fixture or usage example in `README.md`.

## Commit & Pull Request Guidelines

- Commit messages are short, imperative summaries (e.g., "Install mago", "Add initial composer.json setup").
- PRs should include a concise description, the commands you ran (e.g., `vendor/bin/mago analyze`), and any breaking changes.
- If you add or change dependencies, update both `composer.json` and `composer.lock`.

## Configuration Tips

- Front matter parsing uses `league/commonmark` with `symfony/yaml`. Invalid YAML will cause parsing to return `null`.
- Prefer ASCII-only content in source files unless a non-ASCII character is necessary.
