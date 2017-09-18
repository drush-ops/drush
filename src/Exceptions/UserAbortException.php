<?php

namespace Drush\Exceptions;

use Throwable;

class UserAbortException extends \Exception
{
    public function __construct($message = "Cancelled.", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
