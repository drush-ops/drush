<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\StatusCommands;
use Drush\Commands\sql\SqlCommands;

/**
 * Tests sql-connect command
 *
 *   Installs Drupal and checks that the given URL by sql-connect is correct.
 *
 * @group commands
 * @group sql
 */
class SqlConnectTest extends CommandUnishTestCase
{
    public function testSqlConnect()
    {
        $this->setUpDrupal(1, true);
        // Get the connection details with sql-connect and check its structure.
        $this->drush(SqlCommands::CONNECT);
        $connectionString = $this->getOutput();

        // Not all drivers need -e option like sqlite
        $shell_options = "-e";
        $db_driver = $this->dbDriver();
        if ($db_driver == 'mysql') {
            $this->assertMatchesRegularExpression('/^mysql|mariadb --user=[^\s]+ --password=.* --database=[^\s]+ --host=[^\s]+/', $connectionString);
        } elseif ($db_driver == 'sqlite') {
            $this->assertStringContainsString('sqlite3', $connectionString);
            $shell_options = '';
        } elseif ($db_driver == 'pgsql') {
            $this->assertMatchesRegularExpression('/psql -q ON_ERROR_STOP=1  --dbname=[^\s]+ --host=[^\s]+ --port=[^\s]+ --username=[^\s]+/', $connectionString);
        } else {
            $this->markTestSkipped('sql-connect test does not recognize database type in ' . self::getDbUrl());
        }

        if ($db_driver == 'pgsql') {
            $this->markTestSkipped('Postgres prepends PGPASSFILE=/var/www/html/sandbox/tmp/drush_[RANDOM] and that file got deleted already.');
        }

        // Issue a query and check the result to verify the connection.
        $this->execute($connectionString . ' ' . $shell_options . ' "SELECT uid FROM users where uid = 1;"', self::EXIT_SUCCESS, $this->webroot());
        $output = $this->getOutput();
        $this->assertStringContainsString('1', $output);

        // Run 'core-status' and insure that we can bootstrap Drupal.
        $this->drush(StatusCommands::STATUS, [], ['fields' => 'bootstrap']);
        $output = $this->getOutput();
        $this->assertStringContainsString('Successful', $output);

        // Test to see if 'sql-create' can erase the database.
        // The only output is a confirmation string, so we'll run
        // other commands to confirm that this worked.
        $this->drush(SqlCommands::CREATE);

        // Try to execute a query.  This should give a "table not found" error.
        $this->execute($connectionString . ' ' . $shell_options . ' "SELECT uid FROM users where uid = 1;"', self::EXIT_ERROR, $this->webroot());

        // We should still be able to run 'core-status' without getting an
        // error, although Drupal should not bootstrap any longer.
        $this->drush(StatusCommands::STATUS, [], ['fields' => 'bootstrap']);
        $output = $this->getOutput();
        $this->assertStringNotContainsString('Successful', $output);
    }
}
