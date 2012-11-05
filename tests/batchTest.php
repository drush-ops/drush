<?php

/**
 * @file
 *   Tests the drush batch subsystem.
 *
 * @see includes/batch.inc
 *
 * @group base
 */
class batchCase extends Drush_CommandTestCase {

  public function testBatch() {
    $sites = $this->setUpDrupal(1, TRUE);
    $site = reset($sites);
    $root = $this->webroot();
    $uri = key($sites);
    $name = "example";
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'yes' => NULL,
      'include' => dirname(__FILE__),
    );
    $this->drush('unit-batch', array(), $options);
    // Collect log messages that begin with "!!!" (@see: _drush_unit_batch_operation())
    $parsed = parse_backend_output($this->getOutput());
    $special_log_msgs = '';
    foreach ($parsed['log'] as $key => $log) {
      if(substr($log['message'],0,3) == '!!!') {
        $special_log_msgs .= $log['message'];
      }
    }
    $this->assertEquals("!!! ArrayObject does its job.", $special_log_msgs, 'Batch messages were logged');
  }
}
