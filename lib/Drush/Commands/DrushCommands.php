<?php

namespace Drush\Commands;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Common\IO;

abstract class DrushCommands implements IOAwareInterface, LoggerAwareInterface {
  use LoggerAwareTrait;
  use IO {
    io as roboIo;
  }


  public function __construct() {}

  /**
   * Returns a logger object.
   *
   * @return LoggerInterface
   */
  protected function logger()
  {
    return $this->logger;
  }

  /**
   * @todo Override Robo's IO function with our custom style.
   */
  protected function io()
  {
//    if (!$this->io) {
//      $this->io = new TerminusStyle($this->input(), $this->output());
//    }
    return $this->io;
  }

  /**
   * Print the contents of a file.
   *
   * @param string $file
   *   Full path to a file.
   */
  static function printFile($file) {
    // Don't even bother to print the file in --no mode
    if (drush_get_context('DRUSH_NEGATIVE')) {
      return;
    }
    if ((substr($file,-4) == ".htm") || (substr($file,-5) == ".html")) {
      $tmp_file = drush_tempnam(basename($file));
      file_put_contents($tmp_file, drush_html_to_text(file_get_contents($file)));
      $file = $tmp_file;
    }
    // Do not wait for user input in --yes or --pipe modes
    if (drush_get_context('DRUSH_PIPE')) {
      drush_print_pipe(file_get_contents($file));
    }
    elseif (drush_get_context('DRUSH_AFFIRMATIVE')) {
      drush_print(file_get_contents($file));
    }
    elseif (drush_shell_exec_interactive("less %s", $file)) {
      return;
    }
    elseif (drush_shell_exec_interactive("more %s", $file)) {
      return;
    }
    else {
      drush_print(file_get_contents($file));
    }
  }
}
