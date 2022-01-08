<?php

namespace Drush\Drupal;

use Drupal\Core\Update\UpdateKernel as DrupalUpdateKernel;

/**
 * Overridden version of UpdateKernel adapted to the needs of Drush.
 */
class UpdateKernel extends DrupalUpdateKernel
{
    use DrupalKernelTrait;
}
