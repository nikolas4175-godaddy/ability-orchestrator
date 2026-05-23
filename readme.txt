=== Baton ===
Contributors: nikolas4175-godaddy
Tags: workflow, abilities, automation, admin
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chain WordPress Abilities API abilities into saved workflows with field-level data mapping between steps.

== Description ==

A conductor’s wand, properly called a baton, is a thin, lightweight stick used by music directors to amplify and guide physical gestures.

Baton is a WordPress plugin that adds a thin, lightweight layer used by admins to orchestrate site workflows based on the WP [Abilities API](https://developer.wordpress.org/apis/abilities/). 

It provides field-level input mapping to control data passing between registered Abilities, and each workflow is also exposed as it's own Ability (`baton/workflow-{id}`) for nesting or use in external tooling.

**Features:**

* Visual workflow editor (React) with ability steps and data filters between steps
* Input mapping from static workflow input or the previous step's output
* Each published workflow registers as its own ability for nesting and tooling
* Cycle detection when workflows call each other
* Prebuilt editor assets in `build/` — no Node.js required to use the plugin

**Requirements:** WordPress 6.9 or later (Abilities API). PHP 7.4 or later.

== Installation ==

**From WordPress.org**
1. Install Baton through the WordPress plugin directory
2. Activate **Baton** through the ‘Plugins’ menu in WordPress
3. Go to **Tools > Baton** and queue the orchestra!


== Frequently Asked Questions ==

= Why does Baton require WordPress 6.9? =

Baton depends on the Abilities API (`wp_register_ability`, `wp_get_abilities`, and related APIs) introduced in WordPress 6.9. On older versions, the plugin shows an admin notice and does not load workflow features.

= Can I run workflows from the REST API or WP-CLI? =

Each saved workflow is registered as an ability (`baton/workflow-{post_id}`), but workflows are not exposed via `show_in_rest` at this point.

= Do I need npm to use Baton? =

No. Only developers changing files under `src/` need to run `npm install` and `npm run build` to refresh `build/`.

= What happens when I uninstall Baton? =

Baton deletes all `baton_workflow` posts (and their definition meta) when the plugin is removed via the Plugins screen.

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
