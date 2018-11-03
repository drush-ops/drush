<?php

namespace Drupal\Tests;

use Drupal\Core\Extension\ExtensionDiscovery;

/**
 * Allows test classes to require Drupal modules as dependencies.
 *
 * This trait is assumed to be on a subclass of \PHPUnit_Framework_TestCase, and
 * overrides \PHPUnit_Framework_TestCase::checkRequirements(). This allows the
 * test to be marked as skipped before any kernel boot processes have happened.
 */
trait TestRequirementsTrait {

  /**
   * Returns the Drupal root directory.
   *
   * @return string
   */
  protected static function getDrupalRoot() {
    return dirname(dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__))));
  }

  /**
   * Check module requirements for the Drupal use case.
   *
   * This method is assumed to override
   * \PHPUnit_Framework_TestCase::checkRequirements().
   *
   * @throws \PHPUnit_Framework_SkippedTestError
   *   Thrown when the requirements are not met, and this test should be
   *   skipped. Callers should not catch this exception.
   */
  protected function checkRequirements() {
    parent::checkRequirements();

    $root = static::getDrupalRoot();

    // Check if required dependencies exist.
    $annotations = $this->getAnnotations();
    if (!empty($annotations['class']['requires'])) {
      $this->checkModuleRequirements($root, $annotations['class']['requires']);
    }
    if (!empty($annotations['method']['requires'])) {
      $this->checkModuleRequirements($root, $annotations['method']['requires']);
    }
  }

  /**
   * Checks missing module requirements.
   *
   * Iterates through a list of requires annotations and looks for missing
   * modules. The test will be skipped if any of the required modules is
   * missing.
   *
   * @param string $root
   *   The path to the root of the Drupal installation to scan.
   * @param string[] $annotations
   *   A list of requires annotations from either a method or class annotation.
   *
   * @throws \PHPUnit_Framework_SkippedTestError
   *   Thrown when the requirements are not met, and this test should be
   *   skipped. Callers should not catch this exception.
   */
  private function checkModuleRequirements($root, array $annotations) {
    // drupal_valid_ua() might not be loaded.
    require_once $root . '/core/includes/bootstrap.inc';

    // Make a list of required modules.
    $required_modules = [];
    foreach ($annotations as $requirement) {
      if (strpos($requirement, 'module ') === 0) {
        $required_modules[] = trim(str_replace('module ', '', $requirement));
      }
    }

    // If there are required modules, check if they're available.
    if (!empty($required_modules)) {
      // Scan for modules.
      $discovery = new ExtensionDiscovery($root, FALSE);
      $discovery->setProfileDirectories([]);
      $list = array_keys($discovery->scan('module'));
      $not_available = array_diff($required_modules, $list);
      if (!empty($not_available)) {
        throw new \PHPUnit_Framework_SkippedTestError('Required modules: ' . implode(', ', $not_available));
      }
    }
  }

}
