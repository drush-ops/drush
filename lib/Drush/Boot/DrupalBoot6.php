<?php

namespace Drush\Boot;

class DrupalBoot6 extends DrupalBoot {

  function valid_root($path) {
    if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
      // Drupal 7 root.
      $candidate = 'includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/misc/drupal.js')) {
        return $candidate;
      }
    }
  }
}
