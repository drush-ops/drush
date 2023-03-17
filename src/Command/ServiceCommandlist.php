<?php

declare(strict_types=1);

namespace Drush\Command;

/**
 * Keep a list of all of the service commands that we can find when the
 * Drupal Kernel is booted.
 */
class ServiceCommandlist
{
    protected array $commandList = [];

    public function addCommandReference($command): void
    {
        $this->commandList[] = $command;
    }

    public function getCommandList(): array
    {
        return array_filter($this->commandList);
    }
}
