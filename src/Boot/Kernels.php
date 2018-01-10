<?php

namespace Drush\Boot;

use Drush\Drupal\DrupalKernel as DrushDrupalKernel;
use Drush\Drupal\UpdateKernel as DrushUpdateKernel;

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
     * Returns the available kernels.
     */
    public static function availableKernels()
    {
        return [
            static::DRUPAL,
            static::UPDATE,
        ];
    }

    /**
     * Returns the factory method that can be used to retrieve the given kernel.
     *
     * @param string $kernel
     *   The kernel to retrieve.
     *
     * @return callable
     *   The factory method.
     */
    public static function getKernelFactory($kernel)
    {
        $factories = [
            Kernels::DRUPAL => [DrushDrupalKernel::class, 'createFromRequest'],
            Kernels::UPDATE => [DrushUpdateKernel::class, 'createFromRequest'],
        ];
        return $factories[$kernel];
    }
}
