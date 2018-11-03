<?php

namespace Drupal\node\Plugin\Action;

use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\node\NodeInterface;

/**
 * Demotes a node.
 *
 * @Action(
 *   id = "node_unpromote_action",
 *   label = @Translation("Demote selected content from front page"),
 *   type = "node"
 * )
 */
class DemoteNode extends FieldUpdateActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldsToUpdate() {
    return ['promote' => NodeInterface::NOT_PROMOTED];
  }

}
