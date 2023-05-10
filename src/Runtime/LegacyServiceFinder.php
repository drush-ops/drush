<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Drush\Log\Logger;
use Drush\Drush;
use Symfony\Component\Console\Application;
use League\Container\Container as DrushContainer;
use Drush\Config\DrushConfig;
use Composer\Semver\Semver;

/**
 * Find drush.services.yml files.
 *
 * This discovery class is used solely for backwards compatability with
 * Drupal modules that still use drush.services.ymls to define Drush
 * Commands, Generators & etc.; this mechanism is deprecated, though.
 * Modules should instead use the static factory `create` mechanism.
 */
class LegacyServiceFinder
{
    protected $drushServiceYamls = [];

    public function __construct(protected $moduleHandler, protected DrushConfig $drushConfig)
    {
    }

    protected function discoverDrushServiceProviders()
    {
        // Add those Drush service providers from Drush core that
        // need references to the Drupal DI container. This includes
        // Drush commands, and those services needed by those Drush
        // commands.
        //
        // Note that:
        //  - We list all of the individual service files we use here.
        //  - These commands are not available until Drupal is bootstrapped.
        $this->addDrushServiceProvider("_drush__config", Drush::config()->get('drush.base-dir') . '/src/Drupal/Commands/config/drush.services.yml');
        $this->addDrushServiceProvider("_drush__core", Drush::config()->get('drush.base-dir') . '/src/Drupal/Commands/core/drush.services.yml');
        $this->addDrushServiceProvider("_drush__field", Drush::config()->get('drush.base-dir') . '/src/Drupal/Commands/field/drush.services.yml');
        $this->addDrushServiceProvider("_drush__generate", Drush::config()->get('drush.base-dir') . '/src/Drupal/Commands/generate/drush.services.yml');
        $this->addDrushServiceProvider("_drush__pm", Drush::config()->get('drush.base-dir') . '/src/Drupal/Commands/pm/drush.services.yml');
        $this->addDrushServiceProvider("_drush__sql", Drush::config()->get('drush.base-dir') . '/src/Drupal/Commands/sql/drush.services.yml');

        // TODO: We could potentially also add service providers from:
        //  - DRUSH_BASE_PATH . '/drush/drush.services.yml');
        //  - DRUSH_BASE_PATH . '/../drush/drush.services.yml');
        // Or, perhaps better yet, from every Drush command directory
        // (e.g. DRUSH_BASE_PATH/drush/mycmd/drush.services.yml) in
        // any of these `drush` folders. In order to do this, it is
        // necessary that the class files in these commands are available
        // in the autoloader.

        // Also add Drush services from all modules
        $module_filenames = $this->getModuleFileNames();
        // Load each module's serviceProvider class.
        foreach ($module_filenames as $module => $filename) {
            $this->addModuleDrushServiceProvider($module, $filename);
        }
    }

    /**
     * Determine whether or not the Drush services.yml file is applicable
     * for this version of Drush.
     */
    protected function addModuleDrushServiceProvider($module, $filename)
    {
        $serviceYmlPath = $this->findModuleDrushServiceProvider($module, dirname($filename));
        $this->addDrushServiceProvider("_drush.$module", $serviceYmlPath);
    }

    protected function findModuleDrushServiceProvider($module, $dir)
    {
        $services = $this->findModuleDrushServiceProviderFromComposer($dir);
        if (!$services) {
            return $this->findDefaultServicesFile($module, $dir);
        }
        return $this->findAppropriateServicesFile($module, $services, $dir);
    }

    protected function findDefaultServicesFile($module, $dir)
    {
        $result = $dir . "/drush.services.yml";
        if (!file_exists($result)) {
            return;
        }
        Drush::logger()->info(dt("!module should have an extra.drush.services section in its composer.json. See https://www.drush.org/latest/commands/#specifying-the-services-file.", ['!module' => $module]));
        return $result;
    }

    /**
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
     */
    protected function findModuleDrushServiceProviderFromComposer($dir)
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
        Drush::logger()->debug(dt('{module} commands loaded even though its constraint ({constraints}) is incompatible with Drush {version}. Broaden the constraint in {composer} (see \'extra\drush\services\' section) to remove this message.', ['module' => $module, 'composer' => $dir . '/composer.json', 'constraints' => implode(',', $services), 'version' => $version]));
        return $dir . '/' . $serviceYmlPath;
    }

    /**
     * Add a services.yml file if it exists.
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

    public function getDrushServiceFiles()
    {
        if (empty($this->drushServiceYamls)) {
            $this->discoverDrushServiceProviders();
        }
        return $this->drushServiceYamls;
    }

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
