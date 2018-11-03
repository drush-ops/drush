<?php

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a form to test vertical tabs access.
 *
 * @internal
 */
class FormTestVerticalTabsAccessForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_vertical_tabs_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['vertical_tabs1'] = [
      '#type' => 'vertical_tabs',
    ];
    $form['tab1'] = [
      '#type' => 'fieldset',
      '#title' => t('Tab 1'),
      '#collapsible' => TRUE,
      '#group' => 'vertical_tabs1',
    ];
    $form['tab1']['field1'] = [
      '#title' => t('Field 1'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
    ];
    $form['tab2'] = [
      '#type' => 'fieldset',
      '#title' => t('Tab 2'),
      '#collapsible' => TRUE,
      '#group' => 'vertical_tabs1',
    ];
    $form['tab2']['field2'] = [
      '#title' => t('Field 2'),
      '#type' => 'textfield',
      '#default_value' => 'field2',
    ];

    $form['fieldset1'] = [
      '#type' => 'fieldset',
      '#title' => t('Fieldset'),
    ];
    $form['fieldset1']['field3'] = [
      '#type' => 'checkbox',
      '#title' => t('Field 3'),
      '#default_value' => TRUE,
    ];

    $form['container'] = [
      '#type' => 'container',
    ];
    $form['container']['field4'] = [
      '#type' => 'checkbox',
      '#title' => t('Field 4'),
      '#default_value' => TRUE,
    ];
    $form['container']['subcontainer'] = [
      '#type' => 'container',
    ];
    $form['container']['subcontainer']['field5'] = [
      '#type' => 'checkbox',
      '#title' => t('Field 5'),
      '#default_value' => TRUE,
    ];

    $form['vertical_tabs2'] = [
      '#type' => 'vertical_tabs',
    ];
    $form['tab3'] = [
      '#type' => 'fieldset',
      '#title' => t('Tab 3'),
      '#collapsible' => TRUE,
      '#group' => 'vertical_tabs2',
    ];
    $form['tab3']['field6'] = [
      '#title' => t('Field 6'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (empty($values['field1'])) {
      $form_state->setErrorByName('tab1][field1', t('This checkbox inside a vertical tab does not have its default value.'));
    }
    if ($values['field2'] != 'field2') {
      $form_state->setErrorByName('tab2][field2', t('This textfield inside a vertical tab does not have its default value.'));
    }
    if (empty($values['field3'])) {
      $form_state->setErrorByName('fieldset][field3', t('This checkbox inside a fieldset does not have its default value.'));
    }
    if (empty($values['field4'])) {
      $form_state->setErrorByName('container][field4', t('This checkbox inside a container does not have its default value.'));
    }
    if (empty($values['field5'])) {
      $form_state->setErrorByName('container][subcontainer][field5', t('This checkbox inside a nested container does not have its default value.'));
    }
    if (empty($values['field5'])) {
      $form_state->setErrorByName('tab3][field6', t('This checkbox inside a vertical tab whose fieldset access is allowed does not have its default value.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus(t('The form submitted correctly.'));
  }

}
