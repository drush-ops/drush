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
        $this->setUpDrupal(1, true);
        $options = [
            'include' => __DIR__,
        ];
        $this->drush('unit-batch', [], $options);
        $this->assertContains('!!! ArrayObject does its job.', $this->getErrorOutput());
    }
}
