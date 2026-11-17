#!/usr/bin/env bash

composer -n config platform.php 8.3
composer -n require --dev drupal/core-recommended:11.5.x-dev 'phpunit/phpunit:^11.5' --no-update
composer -n update --prefer-lowest
