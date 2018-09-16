<?php
namespace Drush;

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
