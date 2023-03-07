<?php

namespace Unish;

use Drush\Commands\core\StatusCommands;
use Drush\Commands\sql\SqlCommands;

/**
 * @group base
 * @group slow
 */
class SiteInstallTest extends CommandUnishTestCase
{
    /**
     * Test functionality of site set.
     */
    public function testSiteInstallPrefix()
    {
        if ($this->dbDriver() === 'sqlite') {
            $this->markTestSkipped('SQLite do not prefix tables, it prefixes DB storage files');
        } else {
            // Install Drupal in "drupal_" prefix.
            $this->installDrupal('dev', true, ['db-prefix' => 'drupal_']);

            // Run 'core-status' and insure that we can bootstrap Drupal.
            $this->drush(StatusCommands::STATUS, [], ['fields' => 'bootstrap']);
            $output = $this->getOutput();
            $this->assertStringContainsString('Successful', $output);

            // Issue a query and check the result to verify the connection.
            $this->drush(SqlCommands::QUERY, ["SELECT uid FROM drupal_users where uid = 1;"]);
            $output = $this->getOutput();
            $this->assertStringContainsString('1', $output);
        }
    }
}
