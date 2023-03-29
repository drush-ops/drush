<?php

declare(strict_types=1);

namespace Drush\Exceptions;

/**
 * Throw an exception indicating that the command was unable to continue.
 */
class CommandFailedException extends \Exception
{
    public function __construct($message = "Failed.", $code = 1, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
