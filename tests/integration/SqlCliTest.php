<?php

/**
 * @file
 *  Test sql:cli
 */

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * @group slow
 * @group commands
 * @group sql
 */
class SqlCliTest extends UnishIntegrationTestCase
{

    public function testSqlCli()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('On Windows, STDIN redirection is not supported.');
        }
        $this->setUpDrupal(1, true);
        $path = Path::join(__DIR__, 'resources/sqlcli.sql');
        // temporary: just prove that the file exists.
        // why doesn't it get imported below?
        $this->assertFileExists($path);
        // Ensure SQL dumps can be imported via sql:cli.
        $this->drush('sql:cli', [], [], null, $path);
        $this->drush('sql-query', ["SHOW TABLES"]);
        $output = $this->getOutput();
        $this->assertContains('sqlcli', $output);
    }
}
