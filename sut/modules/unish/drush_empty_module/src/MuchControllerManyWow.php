<?php

namespace Drupal\drush_empty_module;

/**
 * Defines a class for a controller.
 */
class MuchControllerManyWow {

  /**
   * Controller callback.
   *
   * @return array
   *   Render array.
   */
  public function sparkles() {
    return [
      '#markup' => 'And there was much sparkling',
    ];
  }

}
