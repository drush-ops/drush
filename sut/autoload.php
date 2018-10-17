<?php

/**
 * @file
 * Includes the autoloader created by Composer.
 *
 * @see composer.json
 * @see index.php
 * @see core/install.php
 * @see core/rebuild.php
 * @see core/modules/statistics/statistics.php
 */

// Use include instead of return because See https://github.com/drush-ops/drush/issues/3741
return include __DIR__ . '/../vendor/autoload.php';
