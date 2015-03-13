<?php

/**
 * @file
 * Contains \Drush\Boot\Drupal8Boot.
 */

namespace Drush\Boot;

use \Drupal\Core\DrupalKernelInterface;

/**
 * Drupal 8 specific bootstrap class.
 */
class Drupal8Boot extends DrupalBoot {

  /**
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  function valid_root($path) {
    if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
      // Drupal 8 root. Additional check for the presence of core/composer.json to
      // grant it is not a Drupal 7 site with a base folder named "core".
      $candidate = 'core/includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/core/core.services.yml')) {
        if (file_exists($path . '/core/misc/drupal.js') || file_exists($path . '/core/assets/js/drupal.js')) {
          return $candidate;
        }
      }
    }
  }

  public function setKernel(DrupalKernelInterface $drupal_kernel) {
    $this->kernel = $drupal_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function terminate() {
    parent::terminate();

    if ($this->kernel) {
      $this->kernel->terminate();
    }
  }

}
