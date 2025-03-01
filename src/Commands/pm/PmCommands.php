<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;

final class PmCommands extends DrushCommands
{
    use AutowireTrait;

    const INSTALL = 'pm:install';
    const UNINSTALL = 'pm:uninstall';
    const LIST = 'pm:list';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected ModuleInstallerInterface $moduleInstaller,
        protected ModuleHandlerInterface $moduleHandler,
        protected ThemeHandlerInterface $themeHandler,
        protected ModuleExtensionList $extensionListModule,
        protected PermissionHandlerInterface $permissionHandler
    ) {
        parent::__construct();
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

    public function getPermissionHandler(): PermissionHandlerInterface
    {
        return $this->permissionHandler;
    }

    /**
     * Enable one or more modules.
     */
    #[CLI\Command(name: self::INSTALL, aliases: ['in', 'install', 'pm-install', 'en', 'pm-enable', 'pm:enable'])]
    #[CLI\Argument(name: 'modules', description: 'A comma delimited list of modules.')]
    #[CLI\Usage(name: 'drush pm:install --simulate content_moderation', description: "Display what modules would be installed but don't install them.")]
    public function install(array $modules): void
    {
        $modules = StringUtils::csvToArray($modules);
        $todo = $this->addInstallDependencies($modules);
        $todo_str = ['!list' => implode(', ', $todo)];
        if ($todo === []) {
            $this->logger()->notice(dt('Already installed: !list', ['!list' => implode(', ', $modules)]));
            return;
        } elseif (Drush::simulate()) {
            $this->output()->writeln(dt('The following module(s) will be installed: !list', $todo_str));
            return;
        } elseif (array_values($todo) !== $modules) {
            $this->output()->writeln(dt('The following module(s) will be installed: !list', $todo_str));
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

        $moduleData = $this->getExtensionListModule()->getList();
        foreach ($todo as $moduleName) {
            $links = $this->getModuleLinks($moduleData[$moduleName]);
            $links = array_map(function ($link) {
                return sprintf('<href=%s>%s</>', $link->getUrl()->setAbsolute()->toString(), $link->getText());
            }, $links);

            if ($links === []) {
                $this->logger()->success(dt('Module %name has been installed.', ['%name' => $moduleName]));
            } else {
                $this->logger()->success(dt('Module %name has been installed. (%links)', ['%name' => $moduleName, '%links' => implode(' - ', $links)]));
            }
        }
    }

    /**
     * Run requirements checks on the module installation.
     * @see \drupal_check_module()
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: PmCommands::INSTALL)]
    public function validateEnableModules(CommandData $commandData): void
    {
        $modules = $commandData->input()->getArgument('modules');
        $modules = StringUtils::csvToArray($modules);
        $modules = $this->addInstallDependencies($modules);
        if ($modules === []) {
            return;
        }

        require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        $error = false;
        foreach ($modules as $module) {
            // Note: we can't just call the API ($moduleHandler->loadInclude($module, 'install')),
            // because the API ignores modules that haven't been installed yet. We have
            // to do it the same way the `function drupal_check_module($module)` does.
            $file = DRUPAL_ROOT . '/' . $this->extensionListModule->getPath($module) . "/$module.install";
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
     */
    #[CLI\Command(name: self::UNINSTALL, aliases: ['un', 'pmu', 'pm-uninstall'])]
    #[CLI\Argument(name: 'modules', description: 'A comma delimited list of modules.')]
    #[CLI\Usage(name: 'drush pm:uninstall --simulate field_ui', description: "Display what modules would be uninstalled but don't uninstall them.")]
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

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: PmCommands::UNINSTALL)]
    public function validateUninstall(CommandData $commandData): void
    {
        $list = [];
        if ($modules = $commandData->input()->getArgument('modules')) {
            $modules = StringUtils::csvToArray($modules);
            if ($validation_reasons = $this->getModuleInstaller()->validateUninstall($modules)) {
                foreach ($validation_reasons as $module => $reasons) {
                    // @phpstan-ignore foreach.nonIterable
                    foreach ($reasons as $reason) {
                        $list[] = "$module: " . (string)$reason;
                    }
                }
                throw new \Exception(implode("/n", $list));
            }
        }
    }

    /**
     * Show a list of available extensions (modules and themes).
     */
    #[CLI\Command(name: self::LIST, aliases: ['pml', 'pm-list'])]
    #[CLI\Option(name: 'type', description: 'Only show extensions having a given type. Choices: module, theme.', suggestedValues: ['module', 'theme'])]
    #[CLI\Option(name: 'status', description: 'Only show extensions having a given status. Choices: enabled or disabled.', suggestedValues: ['enabled', 'disabled'])]
    #[CLI\Option(name: 'core', description: 'Only show extensions that are in Drupal core.')]
    #[CLI\Option(name: 'no-core', description: 'Only show extensions that are not provided by Drupal core.')]
    #[CLI\Option(name: 'package', description: 'Only show extensions having a given project packages (e.g. Development).')]
    #[CLI\FieldLabels(labels: [
        'package' => 'Package',
        'project' => 'Project',
        'display_name' => 'Name',
        'name' => 'Name',
        'type' => 'Type',
        'path' => 'Path',
        'status' => 'Status',
        'version' => 'Version',
    ])]
    #[CLI\DefaultTableFields(fields: ['package', 'display_name', 'status', 'version'])]
    #[CLI\FilterDefaultField(field: 'display_name')]
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
            if ($package_filter !== []) {
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
     * @return string
     *   Status. Values: enabled|disabled.
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

    protected function getModuleLinks(Extension $module): array
    {
        $links = [];

        // Generate link for module's help page. Assume that if a hook_help()
        // implementation exists then the module provides an overview page, rather
        // than checking to see if the page exists, which is costly.
        if ($this->getModuleHandler()->moduleExists('help') && $module->status && $this->getModuleHandler()->hasImplementations('help', $module->getName())) {
            $links[] = Link::fromTextAndUrl(dt('Help'), Url::fromRoute('help.page', ['name' => $module->getName()]));
        }

        // Generate link for module's permissions page.
        // Avoid DI for PermissionHandler until we understand better at https://github.com/drush-ops/drush/issues/6154.
        if ($module->status && Drupal::service(PermissionHandlerInterface::class)->moduleProvidesPermissions($module->getName())) {
            $links[] = Link::fromTextAndUrl(dt('Permissions'), Url::fromRoute('user.admin_permissions.module', ['modules' => $module->getName()]));
        }

        // Generate link for module's configuration page, if it has one.
        if ($module->status && isset($module->info['configure'])) {
            $route_parameters = $module->info['configure_parameters'] ?? [];
            $links[] = Link::fromTextAndUrl(dt('Configure'), Url::fromRoute($module->info['configure'], $route_parameters));
        }

        return $links;
    }
}
