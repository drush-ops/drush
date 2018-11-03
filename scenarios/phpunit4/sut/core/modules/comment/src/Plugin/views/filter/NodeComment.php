<?php

namespace Drupal\comment\Plugin\views\filter;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter based on comment node status.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("node_comment")
 */
class NodeComment extends InOperator {

  public function getValueOptions() {
    $this->valueOptions = [
      CommentItemInterface::HIDDEN => $this->t('Hidden'),
      CommentItemInterface::CLOSED => $this->t('Closed'),
      CommentItemInterface::OPEN => $this->t('Open'),
    ];
    return $this->valueOptions;
  }

}
