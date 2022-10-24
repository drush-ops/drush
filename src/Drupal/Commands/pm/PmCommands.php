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

    public function getConfigFactory(): ConfigFactoryInterface
    {
        return $this->configFactory;
    }

    public function getModuleInstaller(): ModuleInstallerInterface
    {
        return $this->moduleInstaller;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
    }

    public function getThemeHandler(): ThemeHandlerInterface
    {
        return $this->themeHandler;
    }

    public function getExtensionListModule(): ModuleExtensionList
    {
        return $this->extensionListModule;
    }

    /**
     * Enable one or more modules.
     *
     * @command pm:install
     * @param $modules A comma delimited list of modules.
     * @aliases in, install, pm-install, en, pm-enable, pm:enable
     * @bootstrap root
     *
     * @usage drush pm:install --simulate content_moderation
     *    Display what modules would be installed but don't install them.
     */
    public function install(array $modules): void
    {
        $modules = StringUtils::csvToArray($modules);
        $todo = $this->addInstallDependencies($modules);
        $todo_str = ['!list' => implode(', ', $todo)];
        if (empty($todo)) {
            $this->logger()->notice(dt('Already enabled: !list', ['!list' => implode(', ', $modules)]));
            return;
        } elseif (Drush::simulate()) {
            $this->output()->writeln(dt('The following module(s) will be enabled: !list', $todo_str));
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
     * @hook validate pm:install
     *
     * @throws UserAbortException
     * @throws MissingDependencyException
     *
     * @see \drupal_check_module()
     */
    public function validateEnableModules(CommandData $commandData): void
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
            // Note: we can't just call the API ($moduleHandler->loadInclude($module, 'install')),
            // because the API ignores modules that haven't been installed yet. We have
            // to do it the same way the `function drupal_check_module($module)` does.
            $module_list = \Drupal::service('extension.list.module');
            $file = DRUPAL_ROOT . '/' . $module_list->getPath($module) . "/$module.install";
            if (is_file($file)) {
                require_once $file;
            }
            // Once we've loaded the module, we can invoke its requirements hook.
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
            // Allow the user to bypass the install requirements.
            if (!$this->io()->confirm(dt('The module install requirements failed. Do you wish to continue?'), false)) {
                throw new UserAbortException();
            }
        }
    }

    /**
     * Uninstall one or more modules and their dependent modules.
     *
     * @command pm:uninstall
     * @param $modules A comma delimited list of modules.
     * @aliases un,pmu,pm-uninstall
     *
     * @usage drush pm:uninstall --simulate field_ui
     *      Display what modules would be uninstalled but don't uninstall them.
     */
    public function uninstall(array $modules): void
    {
        $modules = StringUtils::csvToArray($modules);

        $installed_modules = array_filter($modules, function ($module) {
            return $this->getModuleHandler()->moduleExists($module);
        });
        if ($installed_modules === []) {
            throw new \Exception(dt('The following module(s) are not installed: !list. No modules to uninstall.', ['!list' => implode(', ', $modules)]));
        }
        if ($installed_modules !== $modules) {
            $this->logger()->warning(dt('The following module(s) are not installed and will not be uninstalled: !list', ['!list' => implode(', ', array_diff($modules, $installed_modules))]));
        }

        $list = $this->addUninstallDependencies($installed_modules);
        if (Drush::simulate()) {
            $this->output()->writeln(dt('The following extensions will be uninstalled: !list', ['!list' => implode(', ', $list)]));
            return;
        }

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
    public function validateUninstall(CommandData $commandData): void
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
     */
    public function pmList($options = ['format' => 'table', 'type' => 'module,theme', 'status' => 'enabled,disabled', 'package' => self::REQ, 'core' => false, 'no-core' => false]): RowsOfFields
    {
        $rows = [];

        $modules = $this->getExtensionListModule()->getList();
        $themes = $this->getThemeHandler()->rebuildThemeData();
        $both = array_merge($modules, $themes);

        $package_filter = StringUtils::csvToArray(strtolower((string) $options['package']));
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
                'display_name' => $extension->info['name'] . ' (' . $extension->getName() . ')',
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
    public function extensionStatus($extension): string
    {
        return $extension->status == 1 ? 'enabled' : 'disabled';
    }

    public function addInstallDependencies($modules): array
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
        $profile = \Drupal::installProfile();
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
