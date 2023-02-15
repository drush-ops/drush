<?php

namespace Unish;

/**
 * Tests Maintenance commands
 *
 * @group commands
 */
class MaintenanceTest extends UnishIntegrationTestCase
{
    public function testMaint()
    {
        $this->drush('maint:set', [1]);
        $this->drush('maint:get');
        $this->assertOutputEquals('1');
        $this->drush('maint:status', [], [], self::EXIT_ERROR_WITH_CLARITY);
        $this->drush('maint:set', [0]);
        $this->drush('maint:get');
        $this->assertOutputEquals('0');
        $this->drush('maint:status', [], [], self::EXIT_SUCCESS);
    }
}
