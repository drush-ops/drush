<?php

namespace Drupal\search\Plugin\views\row;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;

/**
 * Row handler plugin for displaying search results.
 *
 * @ViewsRow(
 *   id = "search_view",
 *   title = @Translation("Search results"),
 *   help = @Translation("Provides a row plugin to display search results.")
 * )
 */
class SearchRow extends RowPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['score'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['score'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display score'),
      '#default_value' => $this->options['score'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    return [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $row,
    ];
  }

}
