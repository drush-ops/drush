<?php

namespace Drupal\views\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Exposed form plugin that provides an exposed form with required input.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @ViewsExposedForm(
 *   id = "input_required",
 *   title = @Translation("Input required"),
 *   help = @Translation("An exposed form that only renders a view if the form contains user input.")
 * )
 */
class InputRequired extends ExposedFormPluginBase {

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['text_input_required'] = ['default' => $this->t('Select any filter and click on Apply to see results')];
    $options['text_input_required_format'] = ['default' => NULL];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['text_input_required'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Text on demand'),
      '#description' => $this->t('Text to display instead of results until the user selects and applies an exposed filter.'),
      '#default_value' => $this->options['text_input_required'],
      '#format' => isset($this->options['text_input_required_format']) ? $this->options['text_input_required_format'] : filter_default_format(),
      '#editor' => FALSE,
    ];
  }

  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $exposed_form_options = $form_state->getValue('exposed_form_options');
    $form_state->setValue(['exposed_form_options', 'text_input_required_format'], $exposed_form_options['text_input_required']['format']);
    $form_state->setValue(['exposed_form_options', 'text_input_required'], $exposed_form_options['text_input_required']['value']);
    parent::submitOptionsForm($form, $form_state);
  }

  protected function exposedFilterApplied() {
    static $cache = NULL;
    if (!isset($cache)) {
      $view = $this->view;
      if (is_array($view->filter) && count($view->filter)) {
        foreach ($view->filter as $filter) {
          if ($filter->isExposed()) {
            $identifier = $filter->options['expose']['identifier'];
            if (isset($view->getExposedInput()[$identifier])) {
              $cache = TRUE;
              return $cache;
            }
          }
        }
      }
      $cache = FALSE;
    }

    return $cache;
  }

  public function preRender($values) {
    // Display the "text on demand" if needed. This is a site builder-defined
    // text to display instead of results until the user selects and applies
    // an exposed filter.
    if (!$this->exposedFilterApplied()) {
      $options = [
        'id' => 'area',
        'table' => 'views',
        'field' => 'area',
        'label' => '',
        'relationship' => 'none',
        'group_type' => 'group',
        // We need to set the "Display even if view has no result" option to
        // TRUE as the input required exposed form plugin will always force an
        // empty result if no exposed filters are applied.
        'empty' => TRUE,
        'content' => [
          // @see \Drupal\views\Plugin\views\area\Text::render()
          'value' => $this->options['text_input_required'],
          'format' => $this->options['text_input_required_format'],
        ],
      ];
      $handler = Views::handlerManager('area')->getHandler($options);
      $handler->init($this->view, $this->displayHandler, $options);
      $this->displayHandler->handlers['empty'] = [
        'area' => $handler,
      ];
      // Override the existing empty result message (if applicable).
      $this->displayHandler->setOption('empty', ['text' => $options]);
    }
  }

  public function query() {
    if (!$this->exposedFilterApplied()) {
      // We return with no query; this will force the empty text.
      $this->view->built = TRUE;
      $this->view->executed = TRUE;
      $this->view->result = [];
    }
    else {
      parent::query();
    }
  }

}
