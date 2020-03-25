<?php

namespace Unish;

/**
 * @group commands
 */
class DeployTest extends UnishIntegrationTestCase
{

    /**
     * A simple test since all the sub-commands are tested elsewhere.
     */
    public function testDeploy()
    {
        // Prep a config directory that will be imported later.
        $this->drush('config:export');
        
        $this->drush('deploy', [], ['debug' => true]);
        $expecteds = ["Database updates start.", 'Config import start.', 'Deploy hook start.', 'Cache rebuild start.'];
        foreach ($expecteds as $expected) {
            $this->assertContains($expected, $this->getErrorOutput());
        }
        $this->assertContains('ss', 'ww');
    }
}
