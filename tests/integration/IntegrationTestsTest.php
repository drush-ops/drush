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
        // Ensure that a verbose run does not affect subsequent runs.
        $this->drush('version', [], ['debug' => null]);
        $this->assertStringContainsString('[info] Starting bootstrap to none', $this->getErrorOutputRaw());
        $this->drush('version');
        $this->assertStringNotContainsString('[info] Starting bootstrap to none', $this->getErrorOutputRaw());
    }
}
