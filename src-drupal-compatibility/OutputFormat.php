<?php

declare(strict_types=1);

namespace Drupal\Core\Command;

/**
 * A copy of core's enum, which Drupal ships from 12.1.
 *
 * Loaded by the fallback autoloader in src/Doctor/autoload_file.php, and only
 * when core does not provide the enum. Remove once 12.1 is the minimum.
 */
enum OutputFormat: string
{
    case Yaml = 'yaml';
    case Json = 'json';
}
