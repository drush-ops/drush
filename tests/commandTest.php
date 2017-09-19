<?php

namespace Unish;

/**
 * @group base
 */
class commandCase extends CommandUnishTestCase {
  /**
   * Assert that minimum bootstrap phase is honored.
   *
   * Not testing dependency on a module since that requires an installed Drupal.
   * Too slow for little benefit.
   */
  public function testRequirementBootstrapPhase() {
    // Assure that core-cron fails when run outside of a Drupal site.
    $return = $this->drush('core-cron', array(), array('quiet' => NULL), NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Assert that unknown options are caught and flagged as errors
   */
  public function testUnknownOptions() {
    // Make sure an ordinary 'version' command works
    $return = $this->drush('version', array(), array('pipe' => NULL));
    // Add an unknown option --magic=1234 and insure it fails
    $return = $this->drush('version', array(), array('pipe' => NULL, 'magic' => 1234), NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Assert that commands depending on unknown commandfiles are detected.
   */
  public function testMissingDrushDependency() {
    $this->markTestSkipped('SYMFONY: Drush dependencies not implemented.');
    $options = array(
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
      'backend' => NULL, // To obtain and parse the error log.
    );
    $this->drush('unit-drush-dependency', array(), $options, NULL, NULL, self::EXIT_ERROR);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertArrayHasKey("DRUSH_COMMANDFILE_DEPENDENCY_ERROR", $parsed['error_log']);
  }

  /**
   * Assert that commands in uninstalled modules throw an error.
   */
  public function testUninstalledModule() {
    $this->markTestSkipped('SYMFONY BACKEND: Test depends on parse_backend_output.');
    $sites = $this->setUpDrupal(1, TRUE);
    $uri = key($sites);
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'backend' => NULL, // To obtain and parse the error log.
    );
    $this->drush('devel-reinstall', array(), $options, NULL, NULL, self::EXIT_ERROR);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertArrayHasKey("DRUSH_COMMAND_NOT_FOUND", $parsed['error_log']);
  }
}
