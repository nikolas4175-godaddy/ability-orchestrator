# TODO: WordPress.org SVN deploy

Pick this up after the plugin slug is approved and you have SVN credentials.

## Prerequisites

- [ ] Plugin approved on WordPress.org with slug `baton` (or note actual slug if different)
- [ ] SVN username + password from [WordPress.org account settings](https://make.wordpress.org/meta/handbook/tools/account-settings/#svn-credentials)
- [ ] `baton.php` `Version` matches `readme.txt` `Stable tag` for the release tag
- [ ] `npm run build` committed if `src/` changed
- [ ] Listing assets present in `.wordpress-org/` (flat files only):
  - `icon-256x256.png`
  - `banner-772x250.png`
  - `screenshot-1.jpg`
  - `screenshot-2.jpg`

## GitHub Actions setup

1. Add repository secrets:
   - `SVN_USERNAME`
   - `SVN_PASSWORD`
2. Copy [`.github/workflows/deploy.yml.example`](../.github/workflows/deploy.yml.example) to `.github/workflows/deploy.yml`.
3. Optional first run: set `dry-run: true` on the 10up action step, push a test tag, confirm the workflow log looks right.
4. Remove `dry-run` and push the real release tag, e.g. `git tag 0.4.0 && git push origin 0.4.0`.

The workflow runs `npm ci`, `npm run build`, `npm run check`, then [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy) using the same [`.distignore`](../.distignore) and [`.wordpress-org/`](../.wordpress-org/) layout as `npm run release:org`.

## Manual release (until deploy.yml is enabled)

```bash
npm run release:org -- --check
```

Upload `release/baton/assets/`, `release/baton/trunk/`, and `release/baton/tags/{version}/` to the matching SVN paths. Set `svn:mime-type` on images (`image/png`, `image/jpeg`) if your SVN client does not.

## Between version tags

To update `readme.txt` or directory assets on `trunk` without a full release, consider [10up/action-wordpress-plugin-readme-assets](https://github.com/10up/action-wordpress-plugin-readme-assets) (linked from the deploy action README).

## Related docs

- [wordpress-org-review.md](wordpress-org-review.md) — guideline pre-flight
- [README.md — WordPress.org directory assets](../README.md#wordpressorg-directory-assets)
