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
    const string CONF = 'sql:conf';
    #[Deprecated(reason: 'Moved', replacement: SqlConnectCommand::NAME)]
    const string CONNECT = 'sql:connect';
    #[Deprecated(reason: 'Moved', replacement: SqlCreateCommand::NAME)]
    const string CREATE = 'sql:create';
    #[Deprecated(reason: 'Moved', replacement: SqlDropCommand::NAME)]
    const string DROP = 'sql:drop';
    #[Deprecated(reason: 'Moved', replacement: SqlCliCommand::NAME)]
    const string CLI = 'sql:cli';
    #[Deprecated(reason: 'Moved', replacement: SqlQueryCommand::NAME)]
    const string QUERY = 'sql:query';
    #[Deprecated(reason: 'Moved', replacement: SqlDumpCommand::NAME)]
    const string DUMP = 'sql:dump';
}
