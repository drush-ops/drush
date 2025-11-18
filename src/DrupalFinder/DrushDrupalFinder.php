<?php

namespace Drush\DrupalFinder;

use Composer\InstalledVersions;
use Drush\Config\Environment;
use Symfony\Component\Filesystem\Path;

/**
 * A replacement for DrupalFinder. We may go back to that once it uses InstalledVersions from Composer.
 */
class DrushDrupalFinder
{
    public function __construct(private readonly Environment $environment)
    {
    }

    /**
     * Get the Drupal root.
     */
    public function getDrupalRoot(): string|false
    {
        $core = InstalledVersions::getInstallPath('drupal/core');
        return $core ? Path::canonicalize(realpath(dirname($core))) : false;
    }

    /**
     * Get the Composer root.
     */
    public function getComposerRoot(): string
    {
        return dirname($this->getVendorDir());
    }

    /**
     * Get the vendor path.
     */
    public function getVendorDir(): string
    {
        return Path::canonicalize(realpath($this->environment->vendorPath()));
    }
}
