<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Drupal\Core\Update\UpdateKernel as DrupalUpdateKernel;

/**
 * Overridden version of UpdateKernel adapted to the needs of Drush.
 */
class UpdateKernel extends DrupalUpdateKernel
{
    // Nothing here anymore, but kept in case we need it later.
}
