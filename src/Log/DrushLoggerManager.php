<?php

declare(strict_types=1);

namespace Drush\Log;

use Consolidation\Log\LoggerManager;
use JetBrains\PhpStorm\Deprecated;

class DrushLoggerManager extends LoggerManager implements SuccessInterface
{
    #[Deprecated('Use \Drush\Style\DrushStyle::success() instead. See See https://www.drush.org/latest/commands/.')]
    public function success(string $message, array $context = array())
    {
        $this->log(self::SUCCESS, $message, $context);
    }
}
