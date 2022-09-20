<?php

namespace Drush\Log;

use Drush\Log\SuccessInterface;
use Consolidation\Log\LoggerManager;

class DrushLoggerManager extends LoggerManager implements SuccessInterface
{
    public function success(string $message, array $context = array())
    {
        $this->log(self::SUCCESS, $message, $context);
    }
}
