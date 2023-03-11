<?php

declare(strict_types=1);

namespace Drush\Log;

interface SuccessInterface
{
    /**
     * Command successfully completed some operation.
     * Displayed at VERBOSITY_NORMAL.
     */
    public const SUCCESS = 'success';

    /**
     * Log a 'success' message.
     */
    public function success(string $message, array $context = array());
}
