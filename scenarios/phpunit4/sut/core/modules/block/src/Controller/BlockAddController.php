<?php

namespace Drupal\block\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for building the block instance add form.
 */
class BlockAddController extends ControllerBase {

  /**
   * Build the block instance add form.
   *
   * @param string $plugin_id
   *   The plugin ID for the block instance.
   * @param string $theme
   *   The name of the theme for the block instance.
   *
   * @return array
   *   The block instance edit form.
   */
  public function blockAddConfigureForm($plugin_id, $theme) {
    // Create a block entity.
    $entity = $this->entityManager()->getStorage('block')->create(['plugin' => $plugin_id, 'theme' => $theme]);

    return $this->entityFormBuilder()->getForm($entity);
  }

}
