<?php

namespace Drupal\webprofiler\Helper;

/**
 * Interface ClassShortenerInterface
 */
interface ClassShortenerInterface {

  /**
   * @param string $class
   *
   * @return string
   */
  public function shortenClass($class);

}
