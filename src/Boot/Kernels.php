<?php

namespace Drush\Boot;

use Drush\Drupal\DrupalKernel as DrushDrupalKernel;
use Drush\Drupal\UpdateKernel as DrushUpdateKernel;
use Drush\Drupal\InstallerKernel as DrushInstallerKernel;

/**
 * Defines the available kernels that can be bootstrapped.
 */
final class Kernels
{
    /**
     * The default kernel that is used on standard requests.
     *
     * @var string
     */
    const DRUPAL = 'drupal';

    /**
     * The kernel that is used during database updates.
     *
     * @var string
     */
    const UPDATE = 'update';

    /**
     * The kernel that is used during site installation.
     *
     * @var string
     */
    const INSTALLER = 'installer';

    /**
     * Returns the available kernels.
     */
    public static function availableKernels(): array
    {
        return [
            static::DRUPAL,
            static::UPDATE,
            static::INSTALLER,
        ];
    }

    /**
     * Returns the factory method that can be used to retrieve the given kernel.
     *
     * @param string $kernel
     *   The kernel to retrieve.
     *
     *   The factory method.
     */
    public static function getKernelFactory(string $kernel): array
    {
        $factories = [
            Kernels::DRUPAL => [DrushDrupalKernel::class, 'createFromRequest'],
            Kernels::UPDATE => [DrushUpdateKernel::class, 'createFromRequest'],
            Kernels::INSTALLER => [DrushInstallerKernel::class, 'createFromRequest'],
        ];
        return $factories[$kernel];
    }
}
