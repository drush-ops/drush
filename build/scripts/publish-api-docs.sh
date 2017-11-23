#!/bin/bash

# Test for master branch, or other branches matching 0.x, 1.x, etc.
BRANCH_REGEX='^\(master\|9\.[0-9x.]*\)$'

# Check to make sure that our build environment is right. Skip with no error otherwise.
test -n "$TRAVIS"                             || { echo "This script is only designed to be run on Travis."; exit 0; }
echo "$TRAVIS_BRANCH" | grep -q $BRANCH_REGEX || { echo "Skipping docs update for branch $TRAVIS_BRANCH - docs only updated for master branch and tagged builds."; exit 0; }
test "$TRAVIS_PULL_REQUEST" == "false"        || { echo "Skipping docs update -- not done on pull requests. (PR #$TRAVIS_PULL_REQUEST)"; exit 0; }
test "${TRAVIS_PHP_VERSION:0:3}" == "5.6"     || { echo "Skipping docs update for PHP $TRAVIS_PHP_VERSION -- only update for PHP 5.6 build."; exit 0; }
test "$TRAVIS_REPO_SLUG" == "drush-ops/drush"   || { echo "Skipping docs update for repository $TRAVIS_REPO_SLUG -- do not build docs for forks."; exit 0; }

# Check our requirements for running this script have been met.
test -n "$GITHUB_TOKEN"                       || { echo "GITHUB_TOKEN environment variable must be set to run this script."; exit 1; }
test -n "$(git config --global user.email)"   || { echo 'Git user email not set. Use `git config --global user.email EMAIL`.'; exit 1; }
test -n "$(git config --global user.name)"    || { echo 'Git user name not set. Use `git config --global user.name NAME`.'; exit 1; }

# Ensure that we exit on failure, and echo lines as they are executed.
# We don't need to see our sanity-checks get echoed, so we turn this on after they are done.
set -ev

# Install Sami using the install script in composer.json
composer sami-install

# Build the API documentation using the api script in composer.json
composer api

# Check out the gh-pages branch using our Github token (defined at https://travis-ci.org/lcache/lcache/settings)
API_BUILD_DIR="$HOME/.drush-build/gh-pages"
if [ ! -d "$API_BUILD_DIR" ]
then
  mkdir -p "$(dirname $API_BUILD_DIR)"
  git clone --quiet --branch=gh-pages https://${GITHUB_TOKEN}@github.com/drush-ops/drush "$API_BUILD_DIR" > /dev/null
fi

# Replace the old 'api' folder with the newly-built API documentation
rm -rf "$API_BUILD_DIR/api"
cp -R api "$API_BUILD_DIR"

# Commit any changes to the documentation
cd "$API_BUILD_DIR"
git add -A api
git commit -m "Update API documentation from Travis build $TRAVIS_BUILD_NUMBER, '$TRAVIS_COMMIT'."
git push