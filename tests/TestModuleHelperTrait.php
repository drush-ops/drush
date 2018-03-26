<?php

namespace Unish;

use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

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
        }
    }
}
