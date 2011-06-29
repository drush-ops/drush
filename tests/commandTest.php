<?php

class commandCase extends Drush_CommandTestCase {
  public function testInvoke() {
    $expected = array(
      'unit_drush_init',
      'drush_unit_invoke_init',
      'drush_unit_invoke_validate',
      'drush_unit_pre_unit_invoke',
      'drush_unit_invoke',
      'drush_unit_post_unit_invoke',
      'drush_unit_post_unit_invoke_rollback',
      'drush_unit_pre_unit_invoke_rollback',
      'drush_unit_invoke_validate_rollback',
    );

    // We expect a return code of 1 so just call execute() directly.
    $exec = sprintf('%s unit-invoke --include=%s', UNISH_DRUSH, self::unish_escapeshellarg(dirname(__FILE__)));
    $this->execute($exec, self::EXIT_ERROR);
    $called = json_decode($this->getOutput());
    $this->assertSame($expected, $called);
  }

  /*
   * Assert that minimum bootstrap phase is honored.
   *
   * Not testing dependency on a module since that requires an installed Drupal.
   * Too slow for little benefit.
   */
  public function testRequirementBootstrapPhase() {
    // Assure that core-cron fails when run outside of a Drupal site.
    $return = $this->execute(UNISH_DRUSH . ' core-cron --quiet', self::EXIT_ERROR);
  }
}