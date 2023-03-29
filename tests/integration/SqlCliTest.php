<?php

declare(strict_types=1);

/**
 * @file
 *  Test sql:cli
 */

namespace Unish;

use Drush\Commands\sql\SqlCommands;
use Drush\Sql\SqlBase;
use Symfony\Component\Filesystem\Path;

/**
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
        $this->drush(SqlCommands::QUERY, [], ['file' => Path::join(__DIR__, 'resources/sqlcli.sql')], self::EXIT_SUCCESS);
        $sql = SqlBase::create();
        $tables = $sql->listTables();
        $this->assertContains('sqlcli', $tables);
    }
}
