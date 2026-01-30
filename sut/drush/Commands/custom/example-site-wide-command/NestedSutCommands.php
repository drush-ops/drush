<?php
namespace Drush\Commands\example_site_wide_command;

use Drush\Commands\DrushCommands;

/**
 * Site-wide commands for the System-Under-Test site
 */

class NestedSutCommands extends DrushCommands
{
    /**
     * Show a fabulous picture.
     *
     * @command sut:nested
     * @hidden
     */
    public function example()
    {
        $this->logger()->notice(dt("This is an example site-wide command committed to the repository in the SUT nested inside a custom/example-site-wide-command directory."));
    }
}
