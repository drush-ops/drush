<?php

namespace Drush\Event;

use Symfony\Component\Console\Application;
use Symfony\Contracts\EventDispatcher\Event;

/*
 * A custom event, mainly for command definition altering.
 */

final class ConsoleDefinitionsEvent extends Event
{
    public function __construct(
        protected Application $application
    ) {
    }

    public function setApplication(Application $application): void
    {
        $this->application = $application;
    }

    public function getApplication(): Application
    {
        return $this->application;
    }
}
