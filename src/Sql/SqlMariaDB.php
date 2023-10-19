<?php

declare(strict_types=1);

namespace Drush\Sql;

use Drush\Exec\ExecTrait;

class SqlMariaDB extends SqlMysql
{
    use ExecTrait;

    public function dumpProgram(): string
    {
        if (self::programExists('mariadb-dump')) {
            return 'mariadb-dump';
        }
        return parent::dumpProgram();
    }
}
