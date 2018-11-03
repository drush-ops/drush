<?php

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Form constructor for testing #type 'machine_name' elements.
 *
 * @internal
 */
class FormTestMachineNameForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_machine_name';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['machine_name_1_label'] = [
      '#type' => 'textfield',
      '#title' => 'Machine name 1 label',
    ];
    $form['machine_name_1'] = [
      '#type' => 'machine_name',
      '#title' => 'Machine name 1',
      '#description' => 'A machine name.',
      '#machine_name' => [
        'source' => ['machine_name_1_label'],
      ],
    ];
    $form['machine_name_2_label'] = [
      '#type' => 'textfield',
      '#title' => 'Machine name 2 label',
    ];
    $form['machine_name_2'] = [
      '#type' => 'machine_name',
      '#title' => 'Machine name 2',
      '#description' => 'Another machine name.',
      '#machine_name' => [
        'source' => ['machine_name_2_label'],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setResponse(new JsonResponse($form_state->getValues()));
  }

}
