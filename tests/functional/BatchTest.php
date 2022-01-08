<?php

namespace Unish;

/**
 * Tests the Drush Batch subsystem.
 *
 * @group base
 */
class BatchTest extends CommandUnishTestCase
{
    public function testBatch()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'include' => __DIR__,
        ];
        $this->drush('unit-batch', [], $options);
        $this->assertStringContainsString('!!! ArrayObject does its job.', $this->getErrorOutput());
    }
}
