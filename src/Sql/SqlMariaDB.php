<?php

declare(strict_types=1);

namespace Drush\Sql;

class SqlMariaDB extends SqlMysql
{
    public function command(): string
    {
        return 'mariadb';
    }

    public function dumpProgram(): string
    {
        return 'mariadb-dump';
    }
}
