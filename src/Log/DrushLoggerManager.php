<?php

declare(strict_types=1);

namespace Drush\Log;

use Consolidation\Log\LoggerManager;
use JetBrains\PhpStorm\Deprecated;

class DrushLoggerManager extends LoggerManager implements SuccessInterface
{
    const DEPRECATED_MESSAGE = 'Use \Drush\Style\DrushStyle::success() instead. See https://www.drush.org/latest/commands/.';

    #[Deprecated(self::DEPRECATED_MESSAGE)]
    public function success(string $message, array $context = array())
    {
        trigger_deprecation('drush/drush', '13.7.0', self::DEPRECATED_MESSAGE);
        $this->log(self::SUCCESS, $message, $context);
    }
}
