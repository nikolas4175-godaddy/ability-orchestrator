# WordPress.org directory pre-submission review

**Plugin:** Baton  
**Version reviewed:** 0.4.0  
**Date:** May 2026  
**Reviewer:** Internal pre-flight (not an official WordPress.org review)

Use this document before requesting a plugin slug or uploading to SVN. Re-run after each release.

## Summary

| Result | Count |
|--------|-------|
| PASS | 16 |
| PASS with notes | 2 |
| FAIL | 0 |
| N/A (pre-submission) | 2 |

**Ready for submission prep:** Yes, pending manual smoke test and contributor WordPress.org username mapping.

---

## Guideline results

### Guideline 1: GPL-Compatible License — **PASS**

- `baton.php` header: `License: GPL-2.0-or-later`, `License URI` set
- [LICENSE](LICENSE) matches
- No proprietary or incompatible bundled libraries in the plugin package

### Guideline 2: Developer Responsibility — **PASS**

- No restored review-team removals; assets are project-owned or GPL-compatible

### Guideline 3: Stable Version in SVN — **N/A until published**

- `readme.txt` `Stable tag: 0.4.0` matches `baton.php` `Version: 0.4.0`
- **Action on publish:** Tag `0.4.0` in SVN; keep `Stable tag` aligned with the tagged release (do not use `trunk` as stable tag)

### Guideline 4: Human-Readable Code — **PASS**

- PHP source in `includes/`, `baton.php` — readable, not obfuscated
- Editor: `src/` (source) + committed `build/index.js` (built bundle)
- `readme.txt` and [CONTRIBUTING.md](../CONTRIBUTING.md) document build steps and GitHub source URL

### Guideline 5: No Trialware — **PASS**

- No license keys, payment gates, or feature locks

### Guideline 6: SaaS Integrations — **PASS**

- No required external SaaS account for core workflow features

### Guideline 7: External Data Collection — **PASS**

- No telemetry or undisclosed data collection in plugin code
- **Note:** Individual abilities from other plugins may call external services; document in site-specific workflows as needed

### Guideline 8: No Remotely Loaded Executable Code — **PASS**

- No `eval`, remote PHP/JS loading, or auto-update from non–WordPress.org servers
- Playground blueprint references GitHub for demos only (not loaded by the installed plugin)

### Guideline 9: Illegal / Offensive Behavior — **PASS**

- No prohibited content

### Guideline 10: No Forced External Links — **PASS**

- Admin UI scoped to **Tools → Baton**; no forced off-site links

### Guideline 11: No Admin Dashboard Hijacking — **PASS**

- Single menu under Tools; no dashboard takeover or unrelated notices

### Guideline 12: No Readme Spam — **PASS**

- `readme.txt` is descriptive; limited, relevant tags (`workflow`, `abilities`, `automation`, `admin`)

### Guideline 13: Use WordPress-Bundled Libraries — **PASS with notes**

- React editor built with `@wordpress/scripts` (standard block-editor toolchain)
- **Note:** `vendor/` is dev-only (Composer); not shipped in the plugin zip for end users

### Guideline 14: SVN Is a Release Repository — **N/A until published**

- **Action on publish:** Do not commit `node_modules/`, `vendor/`, or local dev artifacts to SVN trunk/tags

### Guideline 15: Increment Version Numbers — **PASS**

- Version `0.4.0` consistent in `baton.php`, `BATON_VERSION`, `package.json`, `readme.txt` Stable tag

### Guideline 16: Plugin Must Be Complete at Submission — **PASS**

- [x] `build/index.js` committed — editor works without npm
- [x] `readme.txt` present
- [x] Activation, deactivation, uninstall lifecycle
- [x] Core feature (create/save/run workflow) implemented
- [x] Screenshots in `.wordpress-org/screenshots/` (`screenshot-1.jpg`, `screenshot-2.jpg`)
- [ ] **Manual smoke:** activate on WP 6.9+, save workflow, AJAX run, nest `baton/workflow-{id}`

### Guideline 17: Trademarks and Copyrights — **PASS with notes**

- Name “Baton” is generic (conductor’s baton metaphor), not “WordPress Baton”
- **Action on submit:** Confirm slug `baton` availability; map `Contributors:` in `readme.txt` to your WordPress.org username

### Guideline 18: Directory Rights Reserved — **PASS**

- Standard acknowledgment; no policy conflicts identified

---

## Assets checklist (SVN `assets/`, not in plugin zip)

| File | Location in repo | Notes |
|------|------------------|-------|
| `icon-256x256.png` | `.wordpress-org/icon-256x256.png` | 256×256 |
| `banner-772x250.png` | `.wordpress-org/banner-772x250.png` | 772×250 |
| `screenshot-1.jpg` | `.wordpress-org/screenshots/screenshot-1.jpg` | Caption 1 in `readme.txt` |
| `screenshot-2.jpg` | `.wordpress-org/screenshots/screenshot-2.jpg` | Caption 2 in `readme.txt` |

Copy these into the WordPress.org SVN `assets/` directory when the slug is approved (same filenames; PNG or JPG both accepted). Optional: add `banner-1544x500.png` (@2x) later. Repo layout and SVN mapping: [README.md — WordPress.org directory assets](../README.md#wordpressorg-directory-assets).

## Intentional product choices (not violations)

- Workflow abilities use `show_in_rest => false` (admin-first; see readme FAQ)
- Requires WordPress 6.9+ for Abilities API

## Re-verify before tag

```bash
npm run check
npm run test:php
```

Confirm `Stable tag` in `readme.txt` equals the SVN tag and `Version` in `baton.php`.
