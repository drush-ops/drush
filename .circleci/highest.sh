#!/usr/bin/env bash

composer -n config platform.php --unset
composer -n require --dev drupal/core-recommended:dev-main --no-update
composer -n update --with-all-dependencies
