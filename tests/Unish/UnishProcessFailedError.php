<?php

namespace Unish;

use Symfony\Component\Process\Process;

class UnishProcessFailedError extends \PHPUnit_Framework_AssertionFailedError {
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
