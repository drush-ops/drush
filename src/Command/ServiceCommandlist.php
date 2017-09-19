<?php
namespace Drush\Command;

use Drush\Log\LogLevel;

/**
 * Keep a list of all of the service commands that we can find when the
 * Drupal Kernel is booted.
 */
class ServiceCommandlist
{
    protected $commandList = [];

    public function addCommandReference($command)
    {
        $this->commandList[] = $command;
    }

    public function getCommandList()
    {
        return $this->commandList;
    }
}
