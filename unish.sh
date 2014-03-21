# Run Unish, the test suite for Drush.
cd "${BASH_SOURCE%/*}"/tests && ../vendor/bin/phpunit $@
