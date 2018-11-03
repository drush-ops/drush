<?php

namespace Drupal\form_test;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a test form using autocomplete textfields.
 *
 * @internal
 */
class FormTestAutocompleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_autocomplete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['autocomplete_1'] = [
      '#type' => 'textfield',
      '#title' => 'Autocomplete 1',
      '#autocomplete_route_name' => 'form_test.autocomplete_1',
    ];
    $form['autocomplete_2'] = [
      '#type' => 'textfield',
      '#title' => 'Autocomplete 2',
      '#autocomplete_route_name' => 'form_test.autocomplete_2',
      '#autocomplete_route_parameters' => ['param' => 'value'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
