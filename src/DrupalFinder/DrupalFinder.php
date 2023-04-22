<?php

namespace Drush\DrupalFinder;

use Composer\InstalledVersions;
use Drush\Config\Environment;

/**
 * A replacement for DrupalFinder. We may go back to that once it uses InstalledVersions from Composer.
 */
class DrupalFinder
{

    public function __construct(private Environment $environment)
    {
    }

    /**
     * Get the Drupal root.
     *
     * @return string|bool
     *   The path to the Drupal root, if it was discovered. False otherwise.
     */
    public function getDrupalRoot()
    {
        $core = InstalledVersions::getInstallPath('drupal/core');
        return $core ? dirname($core) : false;
    }

    /**
     * Get the Composer root.
     *
     * @return string|bool
     *   The path to the Composer root, if it was discovered. False otherwise.
     */
    public function getComposerRoot()
    {
        return dirname($this->getVendorDir());
    }

    /**
     * Get the vendor path.
     *
     * @return string|bool
     *   The path to the vendor directory, if it was found. False otherwise.
     */
    public function getVendorDir()
    {
        return $this->environment->vendorPath();
    }
}
