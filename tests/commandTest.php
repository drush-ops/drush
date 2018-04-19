<?php

namespace Unish;

/**
 * @group base
 */
class commandCase extends CommandUnishTestCase {
  public function testInvoke() {
    $expected = array(
      'unit_drush_init',
      'drush_unit_invoke_init',
      'drush_unit_invoke_validate',
      'drush_unit_pre_unit_invoke',
      'drush_unit_invoke_primary',
      // Primary callback is not invoked when command specifies a 'callback'.
      // 'drush_unit_invoke',
      'drush_unit_post_unit_invoke',
      'drush_unit_post_unit_invoke_rollback',
      'drush_unit_pre_unit_invoke_rollback',
      'drush_unit_invoke_validate_rollback',
    );

    $options = array(
      'include' => dirname(__FILE__),
    );
    $this->drush('unit-invoke', array(), $options, NULL, NULL, self::EXIT_ERROR);
    $called = $this->getOutputFromJSON();
    $this->assertSame($expected, $called);
  }

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
    // Finally, add in a hook that uses hook_drush_help_alter to allow the 'magic' option.
    // We need to run 'drush cc drush' to clear the commandfile cache; otherwise, our include will not be found.
    $include_path = dirname(__FILE__) . '/hooks/magic_help_alter';
    $this->drush('version', array(), array('include' => $include_path, 'pipe' => NULL, 'magic' => '1234', 'strict' => NULL));
  }

  /**
   * Assert that errors are thrown for commands with missing callbacks.
   */
  public function testMissingCommandCallback() {
    $options = array(
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
      //'show-invoke' => TRUE,
    );
    $this->drush('missing-callback', array(), $options, NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Assert that commands depending on unknown commandfiles are detected.
   */
  public function testMissingDrushDependency() {
    $options = array(
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
      'backend' => NULL, // To obtain and parse the error log.
    );
    $this->drush('unit-drush-dependency', array(), $options, NULL, NULL, self::EXIT_ERROR);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertArrayHasKey("DRUSH_COMMANDFILE_DEPENDENCY_ERROR", $parsed['error_log']);
  }

  /**
   * Assert that commands in disabled/uninstalled modules throw an error.
   */
  public function testDisabledModule() {
    $sites = $this->setUpDrupal(1, TRUE);
    $uri = key($sites);
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'cache' => NULL,
    );
    $this->drush('pm-download', array('devel'), $options);
    $options += array(
      'backend' => NULL, // To obtain and parse the error log.
    );
    // Assert that this has an error.
    $this->drush('devel-reinstall', array(), $options, NULL, NULL, self::EXIT_ERROR);
  }
}
