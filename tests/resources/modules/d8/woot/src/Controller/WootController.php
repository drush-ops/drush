<?php

/**
 * @file
 * Contains Drupal\woot\Controller\WootController.
 */

namespace Drupal\woot\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class WootController.
 *
 * @package Drupal\woot\Controller
 */
class WootController extends ControllerBase {
  /**
   * Woot.
   *
   * @return string
   *   Return Hello string.
   */
  public function woot() {
    return [
        '#type' => 'markup',
        '#markup' => $this->t('Woot!')
    ];
  }

}
