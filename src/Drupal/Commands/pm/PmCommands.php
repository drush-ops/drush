<?php
namespace Drush\Drupal\Commands\pm;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;

class PmCommands extends DrushCommands
{

    protected $configFactory;

    protected $moduleInstaller;

    protected $moduleHandler;

    protected $themeHandler;

    protected $extensionListModule;

    public function __construct(ConfigFactoryInterface $configFactory, ModuleInstallerInterface $moduleInstaller, ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, ModuleExtensionList $extensionListModule)
    {
        parent::__construct();
        $this->configFactory = $configFactory;
        $this->moduleInstaller = $moduleInstaller;
        $this->moduleHandler = $moduleHandler;
        $this->themeHandler = $themeHandler;
        $this->extensionListModule = $extensionListModule;
    }

    /**
     * @return \Drupal\Core\Config\ConfigFactoryInterface
     */
    public function getConfigFactory()
    {
        return $this->configFactory;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleInstallerInterface
     */
    public function getModuleInstaller()
    {
        return $this->moduleInstaller;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleHandlerInterface
     */
    public function getModuleHandler()
    {
        return $this->moduleHandler;
    }

    /**
     * @return \Drupal\Core\Extension\ThemeHandlerInterface
     */
    public function getThemeHandler()
    {
        return $this->themeHandler;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleExtensionList
     */
    public function getExtensionListModule()
    {
        return $this->extensionListModule;
    }

    /**
     * Enable one or more modules.
     *
     * @command pm:enable
     * @param $modules A comma delimited list of modules.
     * @aliases en,pm-enable
     * @bootstrap root
     */
    public function enable(array $modules)
    {
        $modules = StringUtils::csvToArray($modules);
        $todo = $this->addInstallDependencies($modules);
        $todo_str = ['!list' => implode(', ', $todo)];
        if (empty($todo)) {
            $this->logger()->notice(dt('Already enabled: !list', ['!list' => implode(', ', $modules)]));
            return;
        } elseif (array_values($todo) !== $modules) {
            $this->output()->writeln(dt('The following module(s) will be enabled: !list', $todo_str));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
        }

        if (!$this->getModuleInstaller()->install($modules, true)) {
            throw new \Exception('Unable to install modules.');
        }
        if (batch_get()) {
            drush_backend_batch_process();
        }
        $this->logger()->success(dt('Successfully enabled: !list', $todo_str));
    }

    /**
     * Run requirements checks on the module installation.
     *
     * @hook validate pm:enable
     *
     * @throws \Drush\Exceptions\UserAbortException
     * @throws \Drupal\Core\Extension\MissingDependencyException
     *
     * @see \drupal_check_module()
     */
    public function validateEnableModules(CommandData $commandData)
    {
        $modules = $commandData->input()->getArgument('modules');
        $modules = StringUtils::csvToArray($modules);
        $modules = $this->addInstallDependencies($modules);
        if (empty($modules)) {
            return;
        }

        require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        $error = false;
        foreach ($modules as $module) {
            module_load_install($module);
            $requirements = $this->getModuleHandler()->invoke($module, 'requirements', ['install']);
            if (is_array($requirements) && drupal_requirements_severity($requirements) == REQUIREMENT_ERROR) {
                $error = true;
                $reasons = [];
                foreach ($requirements as $id => $requirement) {
                    if (isset($requirement['severity']) && $requirement['severity'] == REQUIREMENT_ERROR) {
                        $message = $requirement['description'];
                        if (isset($requirement['value']) && $requirement['value']) {
                            $message = dt('@requirements_message (Currently using @item version @version)', ['@requirements_message' => $requirement['description'], '@item' => $requirement['title'], '@version' => $requirement['value']]);
                        }
                        $reasons[$id] = $message;
                    }
                }
                $this->logger()->error(sprintf("Unable to install module '%s' due to unmet requirement(s):%s", $module, "\n  - " . implode("\n  - ", $reasons)));
            }
        }

        if ($error) {
            // Let the user confirm the installation if the requirements are unmet.
            if (!$this->io()->confirm(dt('The module install requirements failed. Do you wish to continue?'))) {
                throw new UserAbortException();
            }
        }
    }

    /**
     * Uninstall one or more modules and their dependent modules.
     *
     * @command pm:uninstall
     * @param $modules A comma delimited list of modules.
     * @aliases pmu,pm-uninstall
     */
    public function uninstall(array $modules)
    {
        $modules = StringUtils::csvToArray($modules);
        $list = $this->addUninstallDependencies($modules);
        if (array_values($list) !== $modules) {
            $this->output()->writeln(dt('The following extensions will be uninstalled: !list', ['!list' => implode(', ', $list)]));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
        }
        if (!$this->getModuleInstaller()->uninstall($modules, true)) {
            throw new \Exception('Unable to uninstall modules.');
        }
        $this->logger()->success(dt('Successfully uninstalled: !list', ['!list' => implode(', ', $list)]));
    }

    /**
     * @hook validate pm-uninstall
     */
    public function validateUninstall(CommandData $commandData)
    {
        if ($modules = $commandData->input()->getArgument('modules')) {
            $modules = StringUtils::csvToArray($modules);
            if ($validation_reasons = $this->getModuleInstaller()->validateUninstall($modules)) {
                foreach ($validation_reasons as $module => $list) {
                    foreach ($list as $markup) {
                        $reasons[$module] = "$module: " . (string) $markup;
                    }
                }
                throw new \Exception(implode("/n", $reasons));
            }
        }
    }

    /**
     * Show a list of available extensions (modules and themes).
     *
     * @command pm:list
     * @option type Only show extensions having a given type. Choices: module, theme.
     * @option status Only show extensions having a given status. Choices: enabled or disabled.
     * @option core Only show extensions that are in Drupal core.
     * @option no-core Only show extensions that are not provided by Drupal core.
     * @option package Only show extensions having a given project packages (e.g. Development).
     * @field-labels
     *   package: Package
     *   project: Project
     *   display_name: Name
     *   name: Name
     *   type: Type
     *   path: Path
     *   status: Status
     *   version: Version
     * @default-fields package,display_name,status,version
     * @aliases pml,pm-list
     * @filter-default-field display_name
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function pmList($options = ['format' => 'table', 'type' => 'module,theme', 'status' => 'enabled,disabled', 'package' => self::REQ, 'core' => false, 'no-core' => false])
    {
        $rows = [];

        $modules = $this->getExtensionListModule()->getList();
        $themes = $this->getThemeHandler()->rebuildThemeData();
        $both = array_merge($modules, $themes);

        $package_filter = StringUtils::csvToArray(strtolower($options['package']));
        $type_filter = StringUtils::csvToArray(strtolower($options['type']));
        $status_filter = StringUtils::csvToArray(strtolower($options['status']));

        foreach ($both as $key => $extension) {
            // Fill in placeholder values as needed.
            $extension->info += ['package' => ''];

            // Filter out test modules/themes.
            if (strpos($extension->getPath(), 'tests')) {
                continue;
            }

            $status = $this->extensionStatus($extension);
            if (!in_array($extension->getType(), $type_filter)) {
                unset($modules[$key]);
                continue;
            }
            if (!in_array($status, $status_filter)) {
                unset($modules[$key]);
                continue;
            }

            // Filter out core if --no-core specified.
            if ($options['no-core']) {
                if ($extension->origin == 'core') {
                    unset($modules[$key]);
                    continue;
                }
            }

            // Filter out non-core if --core specified.
            if ($options['core']) {
                if ($extension->origin != 'core') {
                    unset($modules[$key]);
                    continue;
                }
            }

            // Filter by package.
            if (!empty($package_filter)) {
                if (!in_array(strtolower($extension->info['package']), $package_filter)) {
                    unset($modules[$key]);
                    continue;
                }
            }

            $row = [
                'package' => $extension->info['package'],
                'project' => isset($extension->info['project']) ? $extension->info['project'] : '',
                'display_name' => $extension->info['name']. ' ('. $extension->getName(). ')',
                'name' => $extension->getName(),
                'type' => $extension->getType(),
                'path' => $extension->getPath(),
                'status' => ucfirst($status),
                // Suppress notice when version is not present.
                'version' => @$extension->info['version'],
            ];
            $rows[$key] = $row;
        }

        return new RowsOfFields($rows);
    }

    /**
     * Calculate an extension status based on current status and schema version.
     *
     * @param $extension
     *   Object of a single extension info.
     *
     * @return
     *   String describing extension status. Values: enabled|disabled.
     */
    public function extensionStatus($extension)
    {
        return $extension->status == 1 ? 'enabled' : 'disabled';
    }

    public function addInstallDependencies($modules)
    {
        $module_data = $this->getExtensionListModule()->reset()->getList();
        $module_list  = array_combine($modules, $modules);
        if ($missing_modules = array_diff_key($module_list, $module_data)) {
            // One or more of the given modules doesn't exist.
            throw new MissingDependencyException(sprintf('Unable to install modules %s due to missing modules %s.', implode(', ', $module_list), implode(', ', $missing_modules)));
        }
        $extension_config = $this->getConfigFactory()->getEditable('core.extension');
        $installed_modules = $extension_config->get('module') ?: [];

        // Copied from \Drupal\Core\Extension\ModuleInstaller::install
        // Add dependencies to the list. The new modules will be processed as
        // the while loop continues.
        foreach (array_keys($module_list) as $module) {
            foreach (array_keys($module_data[$module]->requires) as $dependency) {
                if (!isset($module_data[$dependency])) {
                    // The dependency does not exist.
                    throw new MissingDependencyException("Unable to install modules: module '$module' is missing its dependency module $dependency.");
                }

                // Skip already installed modules.
                if (!isset($module_list[$dependency]) && !isset($installed_modules[$dependency])) {
                    $module_list[$dependency] = $dependency;
                }
            }
        }

        // Remove already installed modules.
        $todo = array_diff_key($module_list, $installed_modules);
        return $todo;
    }

    public function addUninstallDependencies($modules)
    {
        // Get all module data so we can find dependencies and sort.
        $module_data = $this->getExtensionListModule()->reset()->getList();
        $module_list = array_combine($modules, $modules);
        if ($diff = array_diff_key($module_list, $module_data)) {
            throw new \Exception(dt('A specified extension does not exist: !diff', ['!diff' => implode(',', $diff)]));
        }
        $extension_config = $this->getConfigFactory()->getEditable('core.extension');
        $installed_modules = $extension_config->get('module') ?: [];

        // Add dependent modules to the list. The new modules will be processed as
        // the while loop continues.
        $profile = drush_drupal_get_profile();
        foreach (array_keys($module_list) as $module) {
            foreach (array_keys($module_data[$module]->required_by) as $dependent) {
                if (!isset($module_data[$dependent])) {
                    // The dependent module does not exist.
                    return false;
                }

                // Skip already uninstalled modules.
                if (isset($installed_modules[$dependent]) && !isset($module_list[$dependent]) && $dependent != $profile) {
                    $module_list[$dependent] = $dependent;
                }
            }
        }
        return $module_list;
    }
}
