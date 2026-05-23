#!/usr/bin/env bash
#
# Prepare a WordPress.org SVN-style release tree from the Git repo.
#
# Mirrors the file selection used by 10up/action-wordpress-plugin-deploy:
#   - trunk: rsync repo with .distignore exclusions
#   - assets: rsync .wordpress-org/ (flat listing files only)
#
# Output (default: release/baton/):
#   assets/              → svn assets/
#   trunk/               → svn trunk/
#   tags/{version}/      → svn tags/{version}/
#
# Also writes release/baton-{version}.zip (folder baton/ inside) for local smoke tests.
#
# When SVN credentials exist, add .github/workflows/deploy.yml using
# 10up/action-wordpress-plugin-deploy (same .distignore and .wordpress-org layout).
#
# Usage:
#   npm run release:org
#   npm run release:org -- --check    # run npm run check before building
#   npm run release:org -- --dry-run  # print actions only
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="baton"
ASSETS_DIR=".wordpress-org"
DISTIGNORE="$ROOT/.distignore"
RUN_CHECK=0
DRY_RUN=0

for arg in "$@"; do
	case "$arg" in
		--check) RUN_CHECK=1 ;;
		--dry-run) DRY_RUN=1 ;;
		-h | --help)
			sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'
			exit 0
			;;
		*)
			echo "Unknown option: $arg" >&2
			exit 1
			;;
	esac
done

version_from_header() {
	grep -E '^\s*\*\s*Version:' "$ROOT/baton.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r'
}

stable_tag_from_readme() {
	grep -E '^Stable tag:' "$ROOT/readme.txt" | head -1 | sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '\r'
}

VERSION="$(version_from_header)"
STABLE="$(stable_tag_from_readme)"

if [[ -z "$VERSION" ]]; then
	echo "Could not read Version from baton.php" >&2
	exit 1
fi

if [[ -z "$STABLE" ]]; then
	echo "Could not read Stable tag from readme.txt" >&2
	exit 1
fi

if [[ "$VERSION" != "$STABLE" ]]; then
	echo "Version mismatch: baton.php=$VERSION readme.txt Stable tag=$STABLE" >&2
	exit 1
fi

if [[ ! -f "$DISTIGNORE" ]]; then
	echo "Missing .distignore (required for release builds)" >&2
	exit 1
fi

required_assets=(
	"$ASSETS_DIR/icon-256x256.png"
	"$ASSETS_DIR/banner-772x250.png"
	"$ASSETS_DIR/screenshot-1.jpg"
	"$ASSETS_DIR/screenshot-2.jpg"
)

for rel in "${required_assets[@]}"; do
	if [[ ! -f "$ROOT/$rel" ]]; then
		echo "Missing directory asset: $rel" >&2
		exit 1
	fi
done

if [[ ! -f "$ROOT/build/index.js" ]]; then
	echo "Missing build/index.js — run npm run build" >&2
	exit 1
fi

OUT="$ROOT/release/$SLUG"
TRUNK="$OUT/trunk"
TAG="$OUT/tags/$VERSION"
ASSETS="$OUT/assets"
ZIP="$ROOT/release/${SLUG}-${VERSION}.zip"

if [[ "$RUN_CHECK" -eq 1 ]]; then
	echo "Running npm run check..."
	if [[ "$DRY_RUN" -eq 1 ]]; then
		echo "[dry-run] npm run check"
	else
		( cd "$ROOT" && npm run check )
	fi
fi

echo "Building WordPress.org release for $SLUG $VERSION"

if [[ "$DRY_RUN" -eq 0 ]]; then
	rm -rf "$OUT" "$ZIP"
	mkdir -p "$TRUNK" "$ASSETS" "$TAG"
else
	echo "[dry-run] rm -rf $OUT $ZIP"
	echo "[dry-run] mkdir -p $TRUNK $ASSETS $TAG"
fi

# Same as 10up deploy: repo → trunk, honoring .distignore.
if [[ "$DRY_RUN" -eq 1 ]]; then
	echo "[dry-run] rsync --exclude-from=$DISTIGNORE $ROOT/ -> $TRUNK/"
else
	rsync -a --exclude-from="$DISTIGNORE" "$ROOT/" "$TRUNK/"
fi

# Same as 10up deploy: .wordpress-org → svn assets/ (flat; no subfolders).
if [[ "$DRY_RUN" -eq 1 ]]; then
	echo "[dry-run] rsync $ROOT/$ASSETS_DIR/ -> $ASSETS/"
else
	rsync -a "$ROOT/$ASSETS_DIR/" "$ASSETS/"
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
	echo "[dry-run] rsync $TRUNK/ -> $TAG/"
else
	rsync -a "$TRUNK/" "$TAG/"
fi

# Zip with baton/ as root folder (standard WP install archive).
if [[ "$DRY_RUN" -eq 1 ]]; then
	echo "[dry-run] zip -r $ZIP (baton/ root)"
else
	STAGE="$ROOT/release/.zip-stage"
	rm -rf "$STAGE"
	mkdir -p "$STAGE/$SLUG"
	rsync -a "$TRUNK/" "$STAGE/$SLUG/"
	( cd "$STAGE" && zip -r "$ZIP" "$SLUG" -x "*.DS_Store" )
	rm -rf "$STAGE"
fi

echo ""
echo "Done."
echo "  SVN layout:  release/$SLUG/"
echo "    assets/    -> wordpress.org/svn/$SLUG/assets/"
echo "    trunk/     -> wordpress.org/svn/$SLUG/trunk/"
echo "    tags/$VERSION/ -> wordpress.org/svn/$SLUG/tags/$VERSION/"
echo "  Local zip:   release/${SLUG}-${VERSION}.zip"
echo ""
echo "Upload: copy each folder to the matching SVN path, then commit."
echo "Future: tag push via 10up/action-wordpress-plugin-deploy (same .distignore + $ASSETS_DIR/)."
