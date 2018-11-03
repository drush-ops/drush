<?php

namespace Drupal\form_test;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a test form object that needs arguments.
 *
 * @internal
 */
class FormTestArgumentsObject extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_form_test_arguments_object';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['form_test.object'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $arg = NULL) {
    $form['element'] = ['#markup' => 'The FormTestArgumentsObject::buildForm() method was used for this form.'];

    $form['bananas'] = [
      '#type' => 'textfield',
      '#default_value' => $arg,
      '#title' => $this->t('Bananas'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The FormTestArgumentsObject::validateForm() method was used for this form.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The FormTestArgumentsObject::submitForm() method was used for this form.'));
    $this->config('form_test.object')
      ->set('bananas', $form_state->getValue('bananas'))
      ->save();
  }

}
