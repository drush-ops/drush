<?php

namespace Drupal\webprofiler\EventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface EventDispatcherTraceableInterface extends EventDispatcherInterface {

  /**
   * @return array
   */
  public function getCalledListeners();

  /**
   * @return mixed
   */
  public function getNotCalledListeners();

}
