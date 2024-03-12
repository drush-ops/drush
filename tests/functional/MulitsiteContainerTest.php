<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\DrupalCommands;
use Drush\Commands\pm\PmCommands;

/**
 * Tests for core commands.
 *
 * @group commands
 */
class MulitsiteContainerTest extends CommandUnishTestCase
{
    public function setup(): void
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
        }
    }

    public function testMultisiteContainer()
    {
        $conf_dir = $this->webroot() . '/sites/dev';
        // Ensure a custom module in a site can't be removed from the container
        // due to container rebuilds.
        mkdir($conf_dir . '/modules');
        mkdir($conf_dir . '/modules/my_module');
        $info = <<<EOT
name: My module
type: module
description: 'Example module'
core_version_requirement: '*'
EOT;
        $module = <<<EOT
<?php

function my_module_cron() {
  \Drupal::messenger()->addMessage("test");
}
EOT;

        $options['uri'] = 'dev';
        file_put_contents($conf_dir . '/modules/my_module/my_module.info.yml', $info);
        file_put_contents($conf_dir . '/modules/my_module/my_module.module', $module);
        $this->drush(PmCommands::INSTALL, ['my_module'], $options);

        $this->drush(DrupalCommands::CRON, [], $options);
        $this->assertStringContainsString('Message: test', $this->getErrorOutput());
        // Change the deployment identifier.
        chmod($conf_dir, 0777);
        chmod($conf_dir . '/settings.php', 0777);
        file_put_contents($conf_dir . '/settings.php', "\n\$settings['deployment_identifier'] = 'a_random_thing';\n", FILE_APPEND);
        $this->drush(DrupalCommands::CRON, [], $options);
        $this->assertStringContainsString('Message: test', $this->getErrorOutput());
    }
}
