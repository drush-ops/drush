<?php

declare(strict_types=1);

namespace Drush\Commands;

/**
 * Machine-readable output formats a command may offer via --format.
 *
 * Mirrors Drupal core's Drupal\Core\Command\OutputFormat so commands can be
 * moved to core unchanged. Replace with the core enum once Drush requires a
 * core version that ships it.
 */
enum OutputFormat: string
{
    case Yaml = 'yaml';
    case Json = 'json';
}
