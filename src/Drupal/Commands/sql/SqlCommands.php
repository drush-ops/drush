<?php

namespace Drush\Drupal\Commands\sql;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

class SqlCommands extends DrushCommands
{
    /**
     * Fetch data via a SQL query.
     *
     * @command sql:fetch
     * @aliases sqlf
     * @usage drush sql:fetch --format=json
     *   Retrieve data in JSON format.
     *
     * @return RowsOfFields
     */
    public function fetch()
    {
        $data = db_query('SELECT * FROM users')->fetchAll();
        return new RowsOfFields($data);
    }
}