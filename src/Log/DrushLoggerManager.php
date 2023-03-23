<?php

declare(strict_types=1);

namespace Drush\Log;

use Consolidation\Log\LoggerManager;

class DrushLoggerManager extends LoggerManager implements SuccessInterface
{
    public function success(string $message, array $context = array())
    {
        $this->log(self::SUCCESS, $message, $context);
    }
}
