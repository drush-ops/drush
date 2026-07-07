#!/usr/bin/env sh
# Make ./drush available on PATH.
export PATH=/var/www/html:$PATH
#@todo make this not fail on Symfony 5-.
# Suppress error output because this command is not present on Symfony 4.
# eval "$(drush completion bash 2>/dev/null)"
