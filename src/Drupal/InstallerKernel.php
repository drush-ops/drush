<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Drupal\Core\Installer\InstallerKernel as DrupalInstallerKernel;

/**
 * Overridden version of InstallerKernel adapted to the needs of Drush.
 */
class InstallerKernel extends DrupalInstallerKernel
{
    use DrupalKernelTrait;
}
