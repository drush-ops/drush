<?php

namespace Unish;

/**
 * Tests support of PostgreSQL schema.
 *
 *   Creates "drupal" schema, installs Drupal, runs core-status and qsl-query.
 *
 * @group commands
 * @group sql
 */
class PostgreSqlSchemaTest extends CommandUnishTestCase
{

    public function testPostgreSqlSchema()
    {
        $this->setUpDrupal(1, true);

        $db_driver = $this->dbDriver();
        if (!$db_driver == 'pgsql') {
          $this->markTestSkipped('PostgreSQL specific test.');
        }
        else {
          // Create schema.
          $this->drush('sql:query', ["CREATE SCHEMA drupal"]);

          // Install Drupal in "drupal" schema.
          $this->installDrupal('dev', true, ['db-prefix' => 'drupal.']);

          // Run 'core-status' and insure that we can bootstrap Drupal.
          $this->drush('core-status', [], ['fields' => 'bootstrap']);
          $output = $this->getOutput();
          $this->assertContains('Successful', $output);

          // Issue a query and check the result to verify the connection.
          $this->drush('sql:query', ["SELECT uid FROM {users} where uid = 1;"], ['db-prefix']);
          $output = $this->getOutput();
          $this->assertContains('1', $output);
        }
    }
}
