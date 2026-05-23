=== Baton ===
Contributors: nikolas4175-godaddy
Tags: workflow, abilities, automation, admin
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chain WordPress Abilities API abilities into saved workflows with field-level data mapping between steps.

== Description ==

Baton is a thin orchestration layer for the [WordPress Abilities API](https://developer.wordpress.org/apis/abilities/). Site administrators compose workflows in **Tools → Baton**, run them from the admin, and reuse saved workflows as nested abilities (`baton/workflow-{id}`).

**Features:**

* Visual workflow editor (React) with ability steps and data filters between steps
* Dot-path input mapping from initial workflow input or the previous step's output
* Each published workflow registers as its own ability for nesting and tooling
* Cycle detection when workflows call each other
* Prebuilt editor assets in `build/` — no Node.js required to use the plugin

**Requirements:** WordPress 6.9 or later (Abilities API). PHP 7.4 or later.

**Source code:** Editor UI source lives in `src/`; run `npm run build` after changes. See [CONTRIBUTING.md](https://github.com/nikolas4175-godaddy/baton/blob/main/CONTRIBUTING.md) and the GitHub repository for development setup.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/baton`, or install from the WordPress.org plugin directory when available.
2. Activate **Baton** through the **Plugins** screen.
3. Open **Tools → Baton** to create and run workflows.

Prebuilt editor assets are included in `build/` — you do not need Node or npm to use the plugin.

== Frequently Asked Questions ==

= Why does Baton require WordPress 6.9? =

Baton depends on the Abilities API (`wp_register_ability`, `wp_get_abilities`, and related APIs) introduced in WordPress 6.9. On older versions, the plugin shows an admin notice and does not load workflow features.

= Can I run workflows from the REST API or WP-CLI? =

Each saved workflow is registered as an ability (`baton/workflow-{post_id}`). Workflows are intentionally not exposed via `show_in_rest` in this release; admin UI and ability execution are the supported paths. REST exposure may be added in a future version if external discovery is needed.

= Do I need npm to use Baton? =

No. Only developers changing files under `src/` need to run `npm install` and `npm run build` to refresh `build/`.

= What happens when I uninstall Baton? =

`uninstall.php` deletes all `baton_workflow` posts (and their definition meta) when the plugin is removed via the Plugins screen.

== Screenshots ==

1. Workflow list under Tools → Baton
2. Visual workflow editor with ability steps and data filters

== Changelog ==

= 0.4.0 =
* Abilities API workflow editor with data filters and input mapping
* Workflow-as-ability registration (`baton/workflow-{id}`)
* PHPUnit, PHPCS, PHPStan, and GitHub Actions CI
* Plugin lifecycle: deactivation hook, uninstall cleanup, recursive input sanitization
* Internationalization: text domain, `languages/baton.pot`

== Upgrade Notice ==

= 0.4.0 =
Initial public release. Requires WordPress 6.9+ for the Abilities API.
