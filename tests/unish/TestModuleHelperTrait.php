<?php

namespace Unish;

/**
 * Helper for installing testing modules.
 */
trait TestModuleHelperTrait
{
    /**
     * Copies the testing modules from a specific path into Drupal.
     *
     * @deprecated No longer used and may soon be removed.
     *
     * @param array $modules A list of testing modules.
     * @param string $sourcePath The path under which the modules are placed.
     */
    public function setupModulesForTests(array $modules, $sourcePath)
    {
    }
}
