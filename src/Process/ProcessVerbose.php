<?php

namespace Drush\Process;

use Drush\Drush;
use Symfony\Component\Process\Process;

class ProcessVerbose extends Process
{
    public function start(callable $callback = null)
    {
        Drush::logger()->info('Executing: ' . $this->getCommandLine());
        return parent::start($callback);
    }

}
