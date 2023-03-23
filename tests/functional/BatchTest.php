<?php

declare(strict_types=1);

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
        $error_output = $this->getErrorOutput();
        $this->assertStringContainsString('!!! ArrayObject does its job.', $error_output);
        $this->assertStringContainsString('Result count is 5', $error_output);
    }
}
