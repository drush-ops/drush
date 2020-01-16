<?php

/**
 * @file
 *  Test sql:cli
 */

namespace Unish;

use Drush\Sql\SqlBase;
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
            $this->markTestSkipped('sql:cli stdin tests do not work on Windows.');
        }

        // @todo Ensure SQL dumps can be imported via sql:cli via stdin.
        $this->drush('sql:query', [], ['file' => Path::join(__DIR__, 'resources/sqlcli.sql')], self::EXIT_SUCCESS);
        $sql = SqlBase::create();
        $tables = $sql->listTables();
        $this->assertContains('sqlcli', $tables);
    }
}
