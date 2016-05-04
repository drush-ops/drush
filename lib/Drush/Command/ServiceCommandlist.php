<?php
namespace Drush\Command;

use Symfony\Component\Console\Command\Command;
use Drush\Log\LogLevel;

/**
 * Keep a list of all of the service commands that we can find when the
 * Drupal Kernel is booted.
 */
class ServiceCommandlist {
    /** @var Symfony\Console\Command\Command[] */
    protected $commandList = [];

    public function addCommandReference(Command $command)
    {
        drush_log(dt("add command reference"), LogLevel::DEBUG);
        $this->commandList[] = $command;
    }

    public function getCommandList()
    {
        return $this->commandList;
    }
}
