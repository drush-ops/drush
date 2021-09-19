<?php

namespace Unish;

/**
 * @coversDefaultClass \Unish\UnishIntegrationTestCase
 * @group tests
 */
class IntegrationTestsTest extends UnishIntegrationTestCase
{
    /**
     * @covers ::drush
     */
    public function testStdErr(): void
    {
        $this->drush('version', [], ['debug' => null]);
        $this->assertStringContainsString('[debug] Starting bootstrap to none', $this->getErrorOutputRaw());
        $this->drush('version');
        $this->assertStringNotContainsString('[debug] Starting bootstrap to none', $this->getErrorOutputRaw());
    }
}
