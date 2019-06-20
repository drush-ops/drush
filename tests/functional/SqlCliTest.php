<?php

/**
 * @file
 *  Test sql:cli
 */

namespace Unish;

/**
 * @group slow
 * @group commands
 * @group sql
 */
class SqlCliTest extends CommandUnishTestCase
{

    public function testSqlCli()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('On Windows, STDIN redirection is not supported.');
        }
        $this->setUpDrupal(1, true);
        // Ensure SQL dumps can be imported via sql:cli.
        $this->drush('sql:cli < ' . __DIR__ . '/resources/sqlcli.sql');
        $this->drush('sql-query', ["SHOW TABLES"]);
        $output = $this->getOutput();
        $this->assertContains('sqlcli', $output);
    }
}
