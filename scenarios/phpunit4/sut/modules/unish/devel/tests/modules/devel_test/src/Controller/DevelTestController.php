<?php

namespace Drupal\devel_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for devel module routes.
 */
class DevelTestController extends ControllerBase {

  /**
   * Returns a simple page output.
   *
   * @return array
   *   A render array.
   */
  public function simplePage() {
    return [
      '#markup' => $this->t('Simple page'),
    ];
  }

}
