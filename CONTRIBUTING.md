# Contributing to Baton

Thank you for helping improve Baton. This project is a WordPress plugin; follow WordPress coding standards and the checks below before opening a pull request.

## Requirements

- WordPress **6.9+** (Abilities API)
- PHP **7.4+**
- [Composer](https://getcomposer.org/) for PHP tooling
- [Node.js](https://nodejs.org/) 20+ only when changing the React editor (`src/`)
- [Docker](https://www.docker.com/) for PHP integration tests (`@wordpress/env`)

## Quick checks (matches CI)

From the plugin root:

```bash
composer install
npm install
npm run check
```

`npm run check` runs PHP syntax, PHPCS, PHPStan, and ESLint on `src/`. It does **not** run PHPUnit.

When you change PHP behavior (runner, mapper, CPT, admin AJAX, abilities registration):

```bash
npx wp-env start   # first time or after env changes
npm run test:php
```

## When you change the editor UI

Files under `src/` compile to `build/` via `@wordpress/scripts`:

```bash
npm run build
```

Commit updated `build/index.js` and `build/index.asset.php` (and any other generated files) so installs without npm still work. This matches [AGENTS.md](AGENTS.md) and WordPress.org Guideline 4 (human-readable / shipped build + source).

## Individual commands

| Task | Command |
|------|---------|
| PHPCS | `composer phpcs` |
| PHPStan | `composer phpstan` |
| PHPUnit | `composer test` or `npm run test:php` |
| ESLint | `npm run lint:js` |
| Editor build | `npm run build` |
| Editor watch | `npm start` |

## Pull request checklist

- [ ] `npm run check` passes
- [ ] `npm run test:php` passes if PHP logic changed
- [ ] `npm run build` committed if `src/` changed
- [ ] User-facing strings use the `baton` text domain and are translatable
- [ ] New PHP files use `declare(strict_types=1);` and match existing `Baton_*` style
- [ ] Scope stays minimal — Baton orchestrates abilities; don't reimplement ability logic from other plugins

## WordPress.org submission

See [docs/wordpress-org-review.md](docs/wordpress-org-review.md) for the guideline pre-flight checklist. Directory assets: [`.wordpress-org/`](.wordpress-org/) (flat image files). Release build: `npm run release:org` (uses [`.distignore`](.distignore)). Future SVN deploy: [`.github/workflows/deploy.yml.example`](.github/workflows/deploy.yml.example).

## Agent / skill context

[AGENTS.md](AGENTS.md) describes architecture, hooks, and conventions for AI-assisted development in this repo.
