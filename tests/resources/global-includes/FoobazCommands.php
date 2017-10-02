<?php
namespace Drush;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

/**
 * Drush will also find global commandfiles that are immediately
 * inside the location specified via --include.
 */
class FoobazCommands
{
    /**
     * Do nearly nothing.
     *
     * @command foobaz
     */
    public function foobaz()
    {
        return 'bar';
    }
}
