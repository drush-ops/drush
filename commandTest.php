<?php

class Core_BatchCase extends Drush_TestCase {
  public function testInvoke() {
    $expected = array(
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
    $this->execute(UNISH_DRUSH . ' unit-invoke --include=' . escapeshellarg(dirname(__FILE__)), 1);
    $called = json_decode($this->getOutput());
    $this->assertSame($expected, $called);
  }
}