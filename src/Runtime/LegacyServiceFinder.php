<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Composer\Semver\Semver;
use Drush\Config\DrushConfig;
use Drush\Drush;

/**
 * Find drush.services.yml files.
 *
 * This discovery class is used solely for backwards compatability with
 * Drupal modules that still use drush.services.ymls to define Drush
 * Commands, Alterers & etc.; this mechanism is deprecated, though.
 * Modules should instead use the static factory `create` mechanism.
 */
class LegacyServiceFinder
{
    protected $drushServiceYamls = [];

    public function __construct(protected $moduleHandler, protected DrushConfig $drushConfig)
    {
    }

    /**
     * Get all service files that this class can discover.
     *
     * @return string[]
     *   List of discovered drush.service.yml files
     */
    public function getDrushServiceFiles(): array
    {
        if (empty($this->drushServiceYamls)) {
            $this->discoverDrushServiceProviders();
        }
        return $this->drushServiceYamls;
    }

    /**
     * Search for drush.service.yml files in discoverable locations.
     */
    protected function discoverDrushServiceProviders()
    {
        // Add Drush services from all modules
        $module_filenames = $this->getModuleFileNames();
        // Load each module's serviceProvider class.
        foreach ($module_filenames as $module => $filename) {
            $this->addModuleDrushServiceProvider($module, $filename);
        }
    }

    /**
     * Determine whether or not the Drush services.yml file is applicable
     * for this version of Drush.
     *
     * @param string $module Module name
     * @param string $filename Full path to modules .info.yml file
     */
    protected function addModuleDrushServiceProvider($module, $filename)
    {
        $serviceYmlPath = $this->findModuleDrushServiceProvider($module, dirname($filename));
        $this->addDrushServiceProvider("_drush.$module", $serviceYmlPath);
    }

    /**
     * List of discovered drush.service.yml files
     *
     * @param $module Module name
     * @param $dir Full path to module base dir
     */
    protected function findModuleDrushServiceProvider(string $module, string $dir): string|null
    {
        $services = $this->findModuleDrushServiceProviderFromComposer($dir);
        if (!$services) {
            return $this->findDefaultServicesFile($module, $dir);
        }
        return $this->findAppropriateServicesFile($module, $services, $dir);
    }

    /**
     * Gets one discovered drush.service.yml file
     *
     * @param string $module Module name
     * @param string $dir Full path to module base dir
     */
    protected function findDefaultServicesFile($module, $dir)
    {
        $result = $dir . "/drush.services.yml";
        if (!file_exists($result)) {
            return;
        }
        return $result;
    }

    /**
     * Get Drush services section from module's composer.json file
     *
     * In composer.json, the Drush version constraints will appear
     * in the 'extra' section like so:
     *
     *   "extra": {
     *     "drush": {
     *       "services": {
     *         "drush.services.yml": "^9"
     *       }
     *     }
     *   }
     *
     * There may be multiple drush service files listed; the first
     * one that has a version constraint that matches the Drush version
     * is used.
     *
     * @param string $dir Full path to module base dir
     */
    protected function findModuleDrushServiceProviderFromComposer($dir): array|false
    {
        $composerJsonPath = "$dir/composer.json";
        if (!file_exists($composerJsonPath)) {
            return false;
        }
        $composerJsonContents = file_get_contents($composerJsonPath);
        $info = json_decode($composerJsonContents, true);
        if (!$info) {
            Drush::logger()->warning(dt('Invalid json in {composer}', ['composer' => $composerJsonPath]));
            return false;
        }
        if (!isset($info['extra']['drush']['services'])) {
            return false;
        }
        return $info['extra']['drush']['services'];
    }

    /**
     * @param string $module Module name
     * @param array $services List of services from module's composer.json file
     * @param string $dir Full path to module base dir
     *
     * @return string
     *   One discovered drush.service.yml file
     */
    protected function findAppropriateServicesFile($module, $services, $dir)
    {
        $version = Drush::getVersion();
        $version = preg_replace('#-dev.*#', '', $version);
        foreach ($services as $serviceYmlPath => $versionConstraint) {
            if (Semver::satisfies($version, $versionConstraint)) {
                Drush::logger()->debug(dt('Found {services} for {module} Drush commands', ['module' => $module, 'services' => $serviceYmlPath]));
                return $dir . '/' . $serviceYmlPath;
            }
        }

        // Regardless, we still return a services file.
        return $dir . '/' . ($serviceYmlPath ?? '');
    }

    /**
     * Add a services.yml file if it exists.
     *
     * @param string $serviceProviderName Arbitrary name for temporary use only
     * @param string $serviceYmlPath Path to drush.services.yml file
     */
    protected function addDrushServiceProvider($serviceProviderName, $serviceYmlPath = '')
    {
        if (($serviceYmlPath !== null) && file_exists($serviceYmlPath)) {
            // Keep our own list of service files
            $this->drushServiceYamls[$serviceProviderName] = $serviceYmlPath;
            // This is how we used to add our drush.services.yml file
            // to the Drush service container. This is no longer necessary.
            //$this->serviceYamls['app'][$serviceProviderName] = $serviceYmlPath;
        }
    }

    /**
     * Find Drupal modules
     *
     * @return string[]
     *   List of paths to all modules' .info.yml files.
     */
    protected function getModuleFileNames()
    {
        $modules = $this->moduleHandler->getModuleList();
        $moduleFilenames = [];

        foreach ($modules as $module => $extension) {
            $moduleFilenames[$module] = $extension->getPathname();
        }

        return $moduleFilenames;
    }
}
