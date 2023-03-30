<?php

namespace Unish;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Helper for installing testing modules.
 */
trait TestModuleHelperTrait
{
    /**
     * Copies the testing modules from a specific path into Drupal.
     *
     * @param array $modules A list of testing modules.
     * @param string $sourcePath The path under which the modules are placed.
     */
    public function setupModulesForTests(array $modules, $sourcePath)
    {
        $webRoot = $this->webroot();
        $fileSystem = new Filesystem();
        foreach ($modules as $module) {
            $sourceDir = Path::join($sourcePath, $module);
            $this->assertFileExists($sourceDir);
            $targetDir = Path::join($webRoot, "modules/unish/$module");
            $fileSystem->mkdir($targetDir);
            $this->recursiveCopy($sourceDir, $targetDir);
            $this->assertFileExists($targetDir);

            // If we are copying a module out of the `core` directory, it
            // might not have the necessary 'core_version_requirement' entry.
            $info_path = $targetDir . "/$module.info.yml";
            $module_info = file_get_contents($info_path);
            if (!str_contains($module_info, 'core_version_requirement')) {
                $module_info = "core_version_requirement: ^8 || ^9 || ^10\n$module_info";
                file_put_contents($info_path, $module_info);
            }
        }
    }

    public function tearDownModulesForTests(array $modules): void {
        $webRoot = $this->webroot();
        $fileSystem = new Filesystem();
        foreach ($modules as $module) {
            $targetDir = Path::join($webRoot, "modules/unish/$module");
            $this->assertFileExists($targetDir);
            $this->recursiveDelete($targetDir);
        }
    }
}
