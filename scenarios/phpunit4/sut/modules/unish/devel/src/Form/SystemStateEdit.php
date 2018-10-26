<?php

namespace Drupal\devel\Form;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form API form to edit a state.
 */
class SystemStateEdit extends FormBase {

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new SystemStateEdit object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devel_state_system_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $state_name = '') {
    // Get the old value
    $old_value = $this->state->get($state_name);

    if (!isset($old_value)) {
      drupal_set_message(t('State @name does not exist in the system.', array('@name' => $state_name)), 'warning');
      return;
    }

    // Only simple structures are allowed to be edited.
    $disabled = !$this->checkObject($old_value);

    if ($disabled) {
      drupal_set_message(t('Only simple structures are allowed to be edited. State @name contains objects.', array('@name' => $state_name)), 'warning');
    }

    // First we will show the user the content of the variable about to be edited.
    $form['value'] = array(
      '#type' => 'item',
      '#title' => $this->t('Current value for %name', array('%name' => $state_name)),
      '#markup' => kpr($old_value, TRUE),
    );

    $transport = 'plain';

    if (!$disabled && is_array($old_value)) {
      try {
        $old_value = Yaml::encode($old_value);
        $transport = 'yaml';
      }
      catch (InvalidDataTypeException $e) {
        drupal_set_message(t('Invalid data detected for @name : %error', array('@name' => $state_name, '%error' => $e->getMessage())), 'error');
        return;
      }
    }

    // Store in the form the name of the state variable
    $form['state_name'] = array(
      '#type' => 'value',
      '#value' => $state_name,
    );
    // Set the transport format for the new value. Values:
    //  - plain
    //  - yaml
    $form['transport'] = array(
      '#type' => 'value',
      '#value' => $transport,
    );

    $form['new_value'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('New value'),
      '#default_value' => $disabled ? '' : $old_value,
      '#disabled' => $disabled,
      '#rows' => 15,
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#disabled' => $disabled,
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('devel.state_system_page')
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if ($values['transport'] == 'yaml') {
      // try to parse the new provided value
      try {
        $parsed_value = Yaml::decode($values['new_value']);
        $form_state->setValue('parsed_value', $parsed_value);
      }
      catch (InvalidDataTypeException $e) {
        $form_state->setErrorByName('new_value', $this->t('Invalid input: %error', array('%error' => $e->getMessage())));
      }
    }
    else {
      $form_state->setValue('parsed_value', $values['new_value']);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the state
    $values = $form_state->getValues();
    $this->state->set($values['state_name'], $values['parsed_value']);

    $form_state->setRedirectUrl(Url::fromRoute('devel.state_system_page'));

    drupal_set_message($this->t('Variable %variable was successfully edited.', array('%variable' => $values['state_name'])));
    $this->logger('devel')->info('Variable %variable was successfully edited.', array('%variable' => $values['state_name']));
  }

  /**
   * Helper function to determine if a variable is or contains an object.
   *
   * @param $data
   *   Input data to check
   *
   * @return bool
   *   TRUE if the variable is not an object and does not contain one.
   */
  protected function checkObject($data) {
    if (is_object($data)) {
      return FALSE;
    }
    if (is_array($data)) {
      // If the current object is an array, then check recursively.
      foreach ($data as $value) {
        // If there is an object the whole container is "contaminated"
        if (!$this->checkObject($value)) {
          return FALSE;
        }
      }
    }

    // All checks pass
    return TRUE;
  }

}
