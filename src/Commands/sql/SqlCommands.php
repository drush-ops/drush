<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Exec\ExecTrait;
use JetBrains\PhpStorm\Deprecated;

final class SqlCommands extends DrushCommands implements StdinAwareInterface
{
    use ExecTrait;
    use StdinAwareTrait;

    #[Deprecated(reason: 'Moved', replacement: SqlConfCommand::NAME)]
    const CONF = 'sql:conf';
    #[Deprecated(reason: 'Moved', replacement: SqlConnectCommand::NAME)]
    const CONNECT = 'sql:connect';
    #[Deprecated(reason: 'Moved', replacement: SqlCreateCommand::NAME)]
    const CREATE = 'sql:create';
    #[Deprecated(reason: 'Moved', replacement: SqlDropCommand::NAME)]
    const DROP = 'sql:drop';
    #[Deprecated(reason: 'Moved', replacement: SqlCliCommand::NAME)]
    const CLI = 'sql:cli';
    #[Deprecated(reason: 'Moved', replacement: SqlQueryCommand::NAME)]
    const QUERY = 'sql:query';
    #[Deprecated(reason: 'Moved', replacement: SqlDumpCommand::NAME)]
    const DUMP = 'sql:dump';
}
