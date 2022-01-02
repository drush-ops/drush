<?php

namespace Drush\Drupal;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Composer\Semver\Semver;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Site\Settings;
use Drush\Drush;
use Drush\Log\LogLevel;

/**
 * Common functionality for overridden kernels.
 */
trait DrupalKernelTrait
{
    /** @var ServiceModifierInterface[] */
    protected $serviceModifiers = [];

    /**
     * Add a service modifier to the container builder.
     *
     * The container is not compiled until $kernel->boot(), so there is a chance
     * for clients to add compiler passes et. al. before then.
     */
    public function addServiceModifier(ServiceModifierInterface $serviceModifier)
    {
        Drush::logger()->debug((dt("Add service modifier")));
        $this->serviceModifiers[] = $serviceModifier;
    }

    /**
     * @inheritdoc
     */
    protected function getContainerBuilder()
    {
        Drush::logger()->debug(dt("Get container builder"));
        $container = parent::getContainerBuilder();
        foreach ($this->serviceModifiers as $serviceModifier) {
            $serviceModifier->alter($container);
        }
        return $container;
    }

    /**
     * Initializes the service container.
     *
     * @return ContainerInterface
     */
    protected function initializeContainer()
    {
        $container_definition = $this->getCachedContainerDefinition();

        if ($this->shouldDrushInvalidateContainer()) {
            // Normally when the container is being rebuilt, the existing
            // container is still available for use until the newly built one
            // replaces it. Certain contrib modules rely on services (like State
            // or the config factory) being available for things like defining
            // event subscriptions.
            // @see https://github.com/drush-ops/drush/issues/3123
            if (isset($container_definition)) {
                $class = Settings::get('container_base_class', '\Drupal\Core\DependencyInjection\Container');
                $container = new $class($container_definition);
                $this->attachSynthetic($container);
                \Drupal::setContainer($container);
            }

            $this->invalidateContainer();
        }
        return parent::initializeContainer();
    }

    protected function shouldDrushInvalidateContainer()
    {
        if (empty($this->moduleList) && !$this->containerNeedsRebuild) {
            $container_definition = $this->getCachedContainerDefinition();
            foreach ($this->serviceModifiers as $serviceModifier) {
                if (!$serviceModifier->check($container_definition)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function discoverServiceProviders()
    {
        // Let Drupal discover all of its service providers
        parent::discoverServiceProviders();

        // Add those Drush service providers from Drush core that
        // need references to the Drupal DI container. This includes
        // Drush commands, and those services needed by those Drush
        // commands.
        //
        // Note that:
        //  - We list all of the individual service files we use here.
        //  - These commands are not available until Drupal is bootstrapped.
        $this->addDrushServiceProvider("_drush__config", DRUSH_BASE_PATH . '/src/Drupal/Commands/config/drush.services.yml');
        $this->addDrushServiceProvider("_drush__core", DRUSH_BASE_PATH . '/src/Drupal/Commands/core/drush.services.yml');
        $this->addDrushServiceProvider("_drush__field", DRUSH_BASE_PATH . '/src/Drupal/Commands/field/drush.services.yml');
        $this->addDrushServiceProvider("_drush__pm", DRUSH_BASE_PATH . '/src/Drupal/Commands/pm/drush.services.yml');
        $this->addDrushServiceProvider("_drush__sql", DRUSH_BASE_PATH . '/src/Drupal/Commands/sql/drush.services.yml');

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
        Drush::logger()->info(dt("!module should have an extra.drush.services section in its composer.json. See http://docs.drush.org/en/10.x/commands/#specifying-the-services-file.", ['!module' => $module]));
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
            $this->serviceYamls['app'][$serviceProviderName] = $serviceYmlPath;
        }
    }
}
