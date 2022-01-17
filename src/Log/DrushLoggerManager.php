<?php

namespace Drush\Log;

use Consolidation\Log\ConsoleLogLevel;
use Consolidation\Log\LoggerManager;

class DrushLoggerManager extends LoggerManager implements SuccessInterface
{
    public function success(string $message, array $context = array())
    {
        $this->log(ConsoleLogLevel::SUCCESS, $message, $context);
    }
}
