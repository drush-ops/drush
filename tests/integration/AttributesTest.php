<?php

namespace Unish;

/**
 * Tests commands defined using PHP 8+ attributes.
 *
 * @group commands
 */
class AttributesTest extends UnishIntegrationTestCase
{
    public function testAttributes()
    {
        $this->drush('test:arithmatic', ['9'], ['include' => __DIR__ . '/resources/']);
        $this->assertOutputEquals('11');
    }
}
