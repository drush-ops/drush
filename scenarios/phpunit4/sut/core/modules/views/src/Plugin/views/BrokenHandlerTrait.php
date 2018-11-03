<?php

namespace Drupal\views\Plugin\views;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * A Trait for Views broken handlers.
 */
trait BrokenHandlerTrait {

  /**
   * Returns this handlers name in the UI.
   *
   * @see \Drupal\views\Plugin\views\PluginBase::defineOptions()
   */
  public function adminLabel($short = FALSE) {
    return t('Broken/missing handler');
  }

  /**
   * The option definition for this handler.
   *
   * @see \Drupal\views\Plugin\views\PluginBase::defineOptions()
   */
  public function defineOptions() {
    return [];
  }

  /**
   * Ensure the main table for this handler is in the query. This is used
   * a lot.
   *
   * @see \Drupal\views\Plugin\views\HandlerBase::ensureMyTable()
   */
  public function ensureMyTable() {
    // No table to ensure.
  }

  /**
   * Modify the views query.
   */
  public function query($group_by = FALSE) {
    /* No query to run */
  }

  /**
   * Provides a form to edit options for this plugin.
   *
   * @see \Drupal\views\Plugin\views\PluginBase::defineOptions()
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $description_top = t('The handler for this item is broken or missing. The following details are available:');

    foreach ($this->definition['original_configuration'] as $key => $value) {
      if (is_scalar($value)) {
        $items[] = new FormattableMarkup('@key: @value', ['@key' => $key, '@value' => $value]);
      }
    }

    $description_bottom = t('Enabling the appropriate module may solve this issue. Otherwise, check to see if there is a module update available.');

    $form['description'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['js-form-item', 'form-item', 'description'],
      ],
      'description_top' => [
        '#markup' => '<p>' . $description_top . '</p>',
      ],
      'detail_list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      'description_bottom' => [
        '#markup' => '<p>' . $description_bottom . '</p>',
      ],
    ];
  }

  /**
   * Determines if the handler is considered 'broken'.
   *
   * This means it's a placeholder used when a handler can't be found.
   *
   * @see \Drupal\views\Plugin\views\HandlerBase::broken()
   */
  public function broken() {
    return TRUE;
  }

  /**
   * Gets dependencies for a broken handler.
   *
   * @return array
   *
   * @see \Drupal\views\Plugin\views\PluginBase::calculateDependencies()
   */
  public function calculateDependencies() {
    return [];
  }

}
