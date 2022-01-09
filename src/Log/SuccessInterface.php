<?php

namespace Drush\Log;

interface SuccessInterface
{
    /**
     * Log a 'success' message.
     */
    public function success(string $message, array $context = array());
}
