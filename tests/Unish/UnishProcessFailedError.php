<?php

namespace Unish;

use Symfony\Component\Process\Process;
use PHPUnit\Framework\AssertionFailedError;

class UnishProcessFailedError extends AssertionFailedError {
  public function __construct($message, Process $process) {
    if ($output = $process->getOutput()) {
      $message .= "\n\nCommand output:\n" . $output;
    }
    if ($stderr = $process->getErrorOutput()) {
      $message .= "\n\nCommand stderr:\n" . $stderr;
    }

    parent::__construct($message);
  }
}
