# Baton

A conductor’s wand, properly called a baton, is a thin, lightweight stick used by music directors to amplify and guide physical gestures.

Baton is a WordPress plugin that adds a thin, lightweight layer used by admins to orchestrate site workflows based on the WP [Abilities API](https://developer.wordpress.org/apis/abilities/). It provides field-level input mapping to control data passing between workflows, and each workflow is also exposed as it's own Ability (`baton/workflow-{id}`) for nesting and external tooling.

## Requirements

- WordPress 6.9+
- PHP 7.4+

## Installation

1. Clone this repository into `wp-content/plugins/baton`.
2. Activate **Baton** in the WordPress Plugins screen.

## Usage

**Tools → Baton** — create workflows, map fields between steps (e.g. `core/get-user-info` `id` → `user_id`), run them from the admin, and reference saved workflows as `baton/workflow-{post_id}` in other workflows.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
