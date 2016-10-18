<?php

namespace Drush\CommandFiles;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Common\IO;

abstract class DrushCommands implements
  IOAwareInterface,
  LoggerAwareInterface
{
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
}
