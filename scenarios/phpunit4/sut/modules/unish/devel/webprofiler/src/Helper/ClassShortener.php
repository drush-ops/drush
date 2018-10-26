<?php

namespace Drupal\webprofiler\Helper;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Class ClassShortener
 */
class ClassShortener implements ClassShortenerInterface {

  /**
   * {@inheritdoc}
   */
  public function shortenClass($class) {
    $parts = explode('\\', $class);
    $result = [];
    $size = count($parts) - 1;

    foreach ($parts as $key => $part) {
      if ($key < $size) {
        $result[] = substr($part, 0, 1);
      }
      else {
        $result[] = $part;
      }
    }

    return new FormattableMarkup("<abbr title=\"@class\">@short</abbr>", [
      '@class' => $class,
      '@short' => implode('\\', $result)
    ]);
  }
}
