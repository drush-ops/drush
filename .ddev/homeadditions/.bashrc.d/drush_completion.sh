#!/usr/bin/env sh
#@todo make this not fail on Symfony 5-.
# Make ./drush available on PATH.
export PATH=/var/www/html:$PATH
# Suppress error output because this command is not present on Symfony 4.
eval "$(drush completion bash 2>/dev/null)"
