<?php

namespace Unish;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for drush_batch_process_time_limit()
 *
 * @group base
 */
class DrushBatchProcessTimeLimitTest extends TestCase
{

    /**
     * Test various DRUSH_BATCH_PROCESS_TIME_LIMIT values.
     *
     * @dataProvider timeProvider
     * @runInSeparateProcess
     */
    public function testFlags($expected, $env = null)
    {
        include_once __DIR__ . '/../../includes/drush.inc';
        if (isset($env)) {
            putenv("DRUSH_BATCH_PROCESS_TIME_LIMIT=" . $env);
        }
        $this->assertEquals($expected, drush_batch_process_time_limit());
    }

    public function timeProvider()
    {
        return [
            'Not set' => [0],
            '0' => [0, 0],
            '0s' => [0, '0s'],
            '10' => [10, '10'],
            '10s' => [10, '10s'],
            '1m' => [60, '1m'],
            '1 m' => [60, '1 m'],
            '10M' => [600, '10M'],
        ];
    }
}
