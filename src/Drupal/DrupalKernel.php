<?php
namespace Drush\Drupal;

use Drush\Log\LogLevel;
use Drupal\Core\DrupalKernel as DrupalDrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

class DrupalKernel extends DrupalDrupalKernel
{
  /** @var ServiceModifierInterface[] */
    protected $serviceModifiers = [];

    /** @var array */
    protected $themeNames;

    /**
     * @inheritdoc
     */
    public static function createFromRequest(Request $request, $class_loader, $environment, $allow_dumping = true, $app_root = null)
    {
        drush_log(dt("Create from request"), LogLevel::DEBUG);
        $kernel = new static($environment, $class_loader, $allow_dumping, $app_root);
        static::bootEnvironment($app_root);
        $kernel->initializeSettings($request);
        return $kernel;
    }

    /**
     * Add a service modifier to the container builder.
     *
     * The container is not compiled until $kernel->boot(), so there is a chance
     * for clients to add compiler passes et. al. before then.
     */
    public function addServiceModifier(ServiceModifierInterface $serviceModifier)
    {
        drush_log(dt("Add service modifier"), LogLevel::DEBUG);
        $this->serviceModifiers[] = $serviceModifier;
    }

    /**
     * @inheritdoc
     */
    protected function getContainerBuilder()
    {
        drush_log(dt("Get container builder"), LogLevel::DEBUG);
        $container = parent::getContainerBuilder();
        foreach ($this->serviceModifiers as $serviceModifier) {
            $serviceModifier->alter($container);
        }
        return $container;
    }

    /**
     * Initializes the service container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function initializeContainer()
    {
        $container_definition = $this->getCachedContainerDefinition();

        if ($this->shouldDrushInvalidateContainer()) {
            $this->invalidateContainer();
        }
        $this->classLoaderAddMultiplePsr4($this->getModuleNamespacesPsr4($this->getThemeFileNames()));
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

        // Also add Drush services from all modules.
        $module_filenames = $this->getModuleFileNames();
        // Load each module's serviceProvider class.
        foreach ($module_filenames as $module => $filename) {
            $filename = dirname($filename) . "/drush.services.yml";
            $this->addDrushServiceProvider("_drush.$module", $filename);
        }

        // Also add Drush services from all themes.
        $theme_filenames = $this->getThemeFileNames();
        // Load each theme's serviceProvider class.
        foreach ($theme_filenames as $theme => $filename) {
            $filename = dirname($filename) . "/drush.services.yml";
            $this->addDrushServiceProvider("_drush.$theme", $filename);
        }
    }

    /**
     * Add a services.yml file if it exists.
     */
    protected function addDrushServiceProvider($serviceProviderName, $filename)
    {
        if (file_exists($filename)) {
            $this->serviceYamls['app'][$serviceProviderName] = $filename;
        }
    }

    /**
     * populates theme data on the filesystem.
     *
     * @see Drupal\Core\DrupalKernel::moduleData().
     */
    protected function themeData($theme_list)
    {
        // First, find profiles.
        $listing = new ExtensionDiscovery($this->root);
        $listing->setProfileDirectories([]);
        $all_profiles = $listing->scan('profile');
        $profiles = array_intersect_key($all_profiles, $theme_list);

        // If a theme is within a profile directory but specifies another
        // profile for testing, it needs to be found in the parent profile.
        $settings = $this->getConfigStorage()->read('simpletest.settings');
        $parent_profile = !empty($settings['parent_profile']) ? $settings['parent_profile'] : null;
        if ($parent_profile && !isset($profiles[$parent_profile])) {
            // In case both profile directories contain the same extension, the
            // actual profile always has precedence.
            $profiles = array($parent_profile => $all_profiles[$parent_profile]) + $profiles;
        }

        $profile_directories = array_map(function ($profile) {
            return $profile->getPath();
        }, $profiles);
        $listing->setProfileDirectories($profile_directories);

        // Now find themes.
        return $listing->scan('theme');
    }

    /**
     * Gets the file name for each enabled theme.
     *
     * @return array
     *   Array where each key is a theme name, and each value is a path to the
     *   respective *.info.yml file.
     */
    protected function getThemeFileNames()
    {
        if ($this->themeNames) {
            return $this->themeNames;
        }
        $extensions = $this->getConfigStorage()->read('core.extension');
        $theme_list = isset($extensions['theme']) ? $extensions['theme'] : [];
        $theme = [];
        $theme_data = $this->themeData($theme_list);
        foreach ($theme_list as $theme => $weight) {
            if (isset($theme_data[$theme])) {
                $this->themeNames[$theme] = $theme_data[$theme]->getPathname();
            }
        }
        return $this->themeNames;
    }
}
