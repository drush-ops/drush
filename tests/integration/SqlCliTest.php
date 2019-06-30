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
        $stdin = file_get_contents(Path::join(__DIR__, 'resources/sqlcli.sql'));
        // Ensure SQL dumps can be imported via sql:cli.
        $this->drush('sql:cli', [], [], null, $stdin);
        $sql = SqlBase::create();
        $tables = $sql->listTables();
        $this->assertContains('sqlcli', $tables);
    }
}
