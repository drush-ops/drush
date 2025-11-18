<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;
use Drupal;

trait PmTrait
{
    protected ConfigFactoryInterface $configFactory;
    protected ModuleExtensionList $extensionListModule;
    protected ModuleHandlerInterface $moduleHandler;
    protected PermissionHandlerInterface $permissionHandler;

    public function addInstallDependencies($modules): array
    {
        $module_data = $this->extensionListModule->reset()->getList();
        $module_list  = array_combine($modules, $modules);
        if ($missing_modules = array_diff_key($module_list, $module_data)) {
            // One or more of the given modules doesn't exist.
            throw new MissingDependencyException(sprintf('Unable to install modules %s due to missing modules %s.', implode(', ', $module_list), implode(', ', $missing_modules)));
        }
        $extension_config = $this->configFactory->getEditable('core.extension');
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

    public function addUninstallDependencies($modules): false|array
    {
        // Get all module data so we can find dependencies and sort.
        $module_data = $this->extensionListModule->reset()->getList();
        $module_list = array_combine($modules, $modules);
        if ($diff = array_diff_key($module_list, $module_data)) {
            throw new \Exception(dt('A specified extension does not exist: !diff', ['!diff' => implode(',', $diff)]));
        }
        $extension_config = $this->configFactory->getEditable('core.extension');
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

    protected function getModuleLinks(Extension $module): array
    {
        $links = [];

        // Generate link for module's help page. Assume that if a hook_help()
        // implementation exists then the module provides an overview page, rather
        // than checking to see if the page exists, which is costly.
        if ($this->moduleHandler->moduleExists('help') && $module->status && $this->moduleHandler->hasImplementations('help', $module->getName())) {
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
