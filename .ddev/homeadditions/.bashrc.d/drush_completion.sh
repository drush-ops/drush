#!/usr/bin/env sh
#@todo make this not fail on Symfony 5-.
# Make ./drush available on PATH.
export PATH=/var/www/html:$PATH
eval "$(drush completion bash)"
