<?php

namespace Drush\Commands;

trait ModuleInstallRequirementsTrait
{
    /**
     * Returns a list of modules not meeting the install requirements.
     *
     * @param string[] $modules
     *   Module list.
     *
     * @return array
     *   List of modules not meeting the install requirements.
     */
    private function hasModuleInstallUnmetRequirements(array $modules): array
    {
        require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';

        $erroneous_modules = [];
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
                $erroneous_modules[$module] = $module;
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

        return $erroneous_modules;
    }
}
