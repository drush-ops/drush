<?php

namespace Unish;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Process\Process;

class UnishProcessFailedError extends AssertionFailedError
{
    public function __construct($message, Process $process)
    {
        if ($output = $process->getOutput()) {
            $message .= "\n\nCommand output:\n" . $output;
        }
        if ($stderr = $process->getErrorOutput()) {
            $message .= "\n\nCommand stderr:\n" . $stderr;
        }

        parent::__construct($message);
    }
}
