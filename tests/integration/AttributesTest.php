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
        
        if (PHP_MAJOR_VERSION < 8) {
            $this->markTestSkipped('PHP8+ only');
        }
        
        $this->drush('test:arithmatic', ['9'], ['include' => __DIR__ . '/resources/']);
        $this->assertOutputEquals("HOOKED\n11");
    }
}
