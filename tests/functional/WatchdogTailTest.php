<?php

namespace Unish;

/**
 * Tests for watchdog tail command.
 *
 * @group commands
 * @group watchdog
 * @group slow
 */
class WatchdogTailTest extends CommandUnishTestCase
{
    /**
     * Test that watchdog tail works.
     */
    public function testWatchdogTail()
    {
        $this->setUpDrupal(1, true);
        $ret = $this->drush('pm-install', ['dblog']);
        $this->assertEquals($ret, 0);
        $options = [];
        $childDrushProcess = $this->drushBackground('watchdog-tail', [], $options + ['simulate' => null]);
        $iteration = 0;
        $expected_output = [];
        do {
            $iteration++;
            $expected_output[$iteration] = "watchdog tail iteration $iteration.";
            $this->drush('php-eval', ["Drupal::logger('drush')->notice('{$expected_output[$iteration]}');"]);
            sleep(2);
            $output = $childDrushProcess->getIncrementalOutput();
            $this->assertStringContainsString($expected_output[$iteration], $output);
            if ($iteration > 1) {
                $this->assertStringNotContainsString($expected_output[$iteration - 1], $output);
            }
        } while ($iteration < 3);
    }
}
