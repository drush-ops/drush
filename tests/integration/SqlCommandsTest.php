<?php

declare(strict_types=1);

/**
 * @file
 *  Test sql:cli
 */

namespace Unish;

use Drush\Commands\sql\SqlCliCommand;
use Drush\Commands\sql\SqlCommands;
use Drush\Sql\SqlBase;
use Symfony\Component\Filesystem\Path;

/**
 * @group commands
 * @group sql
 */
class SqlCommandsTest extends UnishIntegrationTestCase
{
    public function testSqlQuery()
    {

        // @todo Ensure SQL dumps can be imported via sql:cli via stdin.
        $this->drush(SqlCommands::QUERY, [], ['file' => Path::join(__DIR__, 'resources/sqlcli.sql')], self::EXIT_SUCCESS);
        $sql = SqlBase::create();
        $tables = $sql->listTables();
        $this->assertContains('sqlcli', $tables);
    }

    public function testSqlCli()
    {
        // @todo Ensure SQL dumps *cannot* be imported via sql:cli via stdin.
        $this->drush(SqlCliCommand::NAME, [], ['file' => Path::join(__DIR__, 'resources/sqlcli.sql')], self::EXIT_ERROR);
    }
}
