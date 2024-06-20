<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\sql;

use Drush\Commands\sql\sanitize\SanitizePluginInterface as NewSanitizePluginInterface;

/**
 * @deprecated use \Drush\Commands\sql\sanitize\SanitizePluginInterface instead.
 */
interface SanitizePluginInterface extends NewSanitizePluginInterface
{
}
