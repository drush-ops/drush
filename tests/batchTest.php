<?php

namespace Unish;

/**
 * Tests the Drush Batch subsystem.
 *
 * @group base
 */
class batchCase extends CommandUnishTestCase {

  public function testBatch() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($sites),
      'yes' => NULL,
      'include' => __DIR__,
    );
    $this->drush('unit-batch', array(), $options);
    // Collect log messages that begin with "!!!" (@see: _drush_unit_batch_operation())
    $parsed = $this->parse_backend_output($this->getOutput());
    $special_log_msgs = '';
    foreach ($parsed['log'] as $key => $log) {
      if(substr($log['message'],0,3) == '!!!') {
        $special_log_msgs .= $log['message'];
      }
    }
    $this->assertEquals("!!! ArrayObject does its job.", $special_log_msgs, 'Batch messages were logged');
  }
}
