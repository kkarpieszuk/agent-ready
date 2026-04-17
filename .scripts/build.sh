#!/usr/bin/env bash
#
# Build a WordPress.org–ready zip and optionally commit to plugins.svn.wordpress.org.
# Requires: git, rsync, composer, wp (WP-CLI), zip, subversion
# Usage (from plugin repo root): ./.scripts/build.sh [branch]
#
# Set PLUGIN_SLUG to match your plugin’s SVN directory under https://plugins.svn.wordpress.org/
# (the plugin must be approved on WordPress.org before svn checkout works).

set -e

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PLUGIN_SLUG="agent-ready"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
ZIP_NAME="${PLUGIN_SLUG}.zip"
POT_RELATIVE="languages/${PLUGIN_SLUG}.pot"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/replace-version.sh"

# Branch to build from (this repo uses main; pass e.g. develop if you use that workflow).
BRANCH=${1:-main}

read -r -p "$(echo -e '\e[1;31mBuild will be made from branch: '"$BRANCH"'. Continue? \e[0m [y/N]')" response
case "$response" in
    [yY][eE][sS]|[yY])
        echo "Continuing..."
        ;;
    *)
        exit 1
        ;;
esac

git stash
git checkout "$BRANCH"
git pull

VERSION=$(grep -oP '(?<=Stable tag: ).*' readme.txt)

read -r -p "$(echo -e '\e[1;31mPlugin version: '"$VERSION"'. Continue? \e[0m [y/N]')" response
case "$response" in
    [yY][eE][sS]|[yY])
        echo "Continuing..."
        ;;
    *)
        exit 1
        ;;
esac

rm -rf build
mkdir -p build
mkdir -p build/gitversion

rsync -av \
  --exclude='build' \
  --exclude='.git' \
  --exclude='.cursor' \
  --exclude='.claude' \
  . build/gitversion/

cd build/gitversion

rm -rf vendor
composer install --no-dev --no-interaction --optimize-autoloader

mkdir -p languages
wp i18n make-pot . "$POT_RELATIVE"

# Remove files not shipped to WordPress.org (aligned with wc-price-history release layout).
rm -rf .git .github .husky .scripts node_modules tests \
  .gitignore .phpunit.result.cache composer.* \
  package-lock.json package.json phpunit.xml phpunit.xml.dist \
  phpstan.neon phpstan.neon.dist phpstan-custom-rules \
  README.md docs

replace_version_number
replace_version_always_top

zip -r "../$ZIP_NAME" .

cd ..

svn checkout "$SVN_URL" svn-checkout

cp -r gitversion/* svn-checkout/trunk/

cd svn-checkout

svn status

for file in $(svn status | grep '?' | awk '{print $2}'); do
  echo "Adding $file to svn"
  svn add "$file"
done

svn status

read -r -p "$(echo -e '\e[1;31mCommit changes to WordPress.org? \e[0m [y/N]')" response
case "$response" in
    [yY][eE][sS]|[yY])
        echo "Continuing..."
        ;;
    *)
        exit 1
        ;;
esac

svn ci -m "Pushing $VERSION to the trunk"

svn cp trunk "tags/$VERSION"

svn ci -m "Tagging and releasing $VERSION"
