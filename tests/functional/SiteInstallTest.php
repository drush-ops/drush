<?php

namespace Unish;

/**
 * @group base
 * @group slow
 */
class SiteInstallCommandCase extends CommandUnishTestCase
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
            $this->drush('core-status', [], ['fields' => 'bootstrap']);
            $output = $this->getOutput();
            $this->assertContains('Successful', $output);

            // Issue a query and check the result to verify the connection.
            $this->drush('sql:query', ["SELECT uid FROM drupal_users where uid = 1;"]);
            $output = $this->getOutput();
            $this->assertContains('1', $output);
        }
    }
}
