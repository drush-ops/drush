<?php

namespace Drupal\views\Plugin\views\area;

use Drupal\views\Plugin\views\BrokenHandlerTrait;

/**
 * A special handler to take the place of missing or broken handlers.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("broken")
 */
class Broken extends AreaPluginBase {
  use BrokenHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    // Simply render nothing by returning an empty render array.
    return [];
  }

}
