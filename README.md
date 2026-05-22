![Baton — orchestrate WordPress workflows with the Abilities API](assets/images/baton-banner.png)

# Baton

A conductor’s wand, properly called a baton, is a thin, lightweight stick used by music directors to amplify and guide physical gestures.

Baton is a WordPress plugin that adds a thin, lightweight layer used by admins to orchestrate site workflows based on the WP [Abilities API](https://developer.wordpress.org/apis/abilities/). It provides field-level input mapping to control data passing between registered Abilities, and each workflow is also exposed as it's own Ability (`baton/workflow-{id}`) for nesting or use in external tooling.

## Requirements

- WordPress 6.9+
- PHP 7.4+

## Try it on Playground

[![Try Baton on WordPress Playground](https://img.shields.io/badge/Try%20it%20on-WordPress%20Playground-blue)](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fnikolas4175-godaddy%2Fbaton%2Fmain%2Fblueprint.json)

Opens a disposable WordPress 6.9 site with Baton installed and lands on **Tools → Baton**.

## Installation

1. Clone this repository into `wp-content/plugins/baton`.
2. Activate **Baton** in the WordPress Plugins screen.

Prebuilt editor assets are included in `build/` — you do not need Node or npm to use the plugin.

## Usage

**Tools → Baton** — create workflows in the visual editor (vertical step cards, data filters on connectors), run them from the admin, and reference saved workflows as `baton/workflow-{post_id}` in other workflows.

## Building the editor UI

Only needed when changing files under `src/`. The workflow editor is a React bundle built with `@wordpress/scripts`:

```bash
cd wp-content/plugins/baton   # or your clone path
npm install
npm run build
```

After making any changes within `src/`, run `npm run build` (or `npm start` while developing). The built files land in `build/` and are enqueued on the workflow edit screen.

## Development

### Before you push (fast static checks, no git hooks)

Matches the **php-lint** and **js** CI jobs — usually a few seconds, no Docker:

```bash
composer install
npm install
npm run check
```

That runs PHP syntax (`php -l`), PHPCS, PHPStan, and ESLint on `src/`. Run integration tests separately when you change PHP behavior:

```bash
npm run test:php
```

### Individual commands

PHP (Composer):

```bash
composer install
composer check      # syntax + phpcs + phpstan
composer phpcs
composer phpstan
```

Integration tests (requires [Docker](https://www.docker.com/) for `@wordpress/env`):

```bash
npm install
npx wp-env start
npm run test:php
```

JavaScript:

```bash
npm run lint:js
npm run build
```

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
