<?php

namespace Drupal\user\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Filter handler for the current user.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("user_current")
 */
class Current extends BooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->value_value = $this->t('Is the logged in user');
  }

  public function query() {
    $this->ensureMyTable();

    $field = $this->tableAlias . '.' . $this->realField . ' ';
    $or = new Condition('OR');

    if (empty($this->value)) {
      $or->condition($field, '***CURRENT_USER***', '<>');
      if ($this->accept_null) {
        $or->isNull($field);
      }
    }
    else {
      $or->condition($field, '***CURRENT_USER***', '=');
    }
    $this->query->addWhere($this->options['group'], $or);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    // This filter depends on the current user.
    $contexts[] = 'user';

    return $contexts;
  }

}
