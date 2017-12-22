<?php

namespace Unish;

/**
 * Tests the Drush Batch subsystem.
 *
 * @group base
 */
class BatchCase extends CommandUnishTestCase
{

    public function testBatch()
    {
        $sites = $this->setUpDrupal(1, true);
        $options = [
        'include' => __DIR__,
        ];
        $this->drush('unit-batch', [], $options);
        // Collect log messages that begin with "!!!" (@see: _drushUnitBatchOperation())
        $parsed = $this->parseBackendOutput($this->getOutput());
        $special_log_msgs = '';
        foreach ($parsed['log'] as $key => $log) {
            if (substr($log['message'], 0, 3) == '!!!') {
                $special_log_msgs .= $log['message'];
            }
        }
        $this->assertEquals("!!! ArrayObject does its job.", $special_log_msgs, 'Batch messages were logged');
    }
}
