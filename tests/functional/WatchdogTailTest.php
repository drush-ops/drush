<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\PhpCommands;
use Drush\Commands\pm\PmCommands;

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
        $ret = $this->drush(PmCommands::INSTALL, ['dblog']);
        $options = [];
        $childDrushProcess = $this->drushBackground('watchdog:tail', [], $options + ['simulate' => null]);
        $iteration = 0;
        $expected_output = [];
        do {
            $iteration++;
            $expected_output[$iteration] = "watchdog tail iteration $iteration.";
            $this->drush(PhpCommands::EVAL, ["Drupal::logger('drush')->notice('{$expected_output[$iteration]}');"]);
            sleep(3);
            $output = $childDrushProcess->getIncrementalOutput();
            $this->assertStringContainsString($expected_output[$iteration], $output);
            if ($iteration > 1) {
                $this->assertStringNotContainsString($expected_output[$iteration - 1], $output);
            }
        } while ($iteration < 3);
    }
}
