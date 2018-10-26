<?php

namespace Drupal\devel\Form;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Edit config variable form.
 */
class ConfigEditor extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devel_config_system_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config_name = '') {
    $config = $this->config($config_name);

    if ($config === FALSE || $config->isNew()) {
      drupal_set_message(t('Config @name does not exist in the system.', array('@name' => $config_name)), 'error');
      return;
    }

    $data = $config->getOriginal();

    if (empty($data)) {
      drupal_set_message(t('Config @name exists but has no data.', array('@name' => $config_name)), 'warning');
      return;
    }

    try {
      $output = Yaml::encode($data);
    }
    catch (InvalidDataTypeException $e) {
      drupal_set_message(t('Invalid data detected for @name : %error', array('@name' => $config_name, '%error' => $e->getMessage())), 'error');
      return;
    }

    $form['current'] = array(
      '#type' => 'details',
      '#title' => $this->t('Current value for %variable', array('%variable' => $config_name)),
      '#attributes' => array('class' => array('container-inline')),
    );
    $form['current']['value'] = array(
      '#type' => 'item',
      '#markup' => dpr($output, TRUE),
    );

    $form['name'] = array(
      '#type' => 'value',
      '#value' => $config_name,
    );
    $form['new'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('New value'),
      '#default_value' => $output,
      '#rows' => 24,
      '#required' => TRUE,
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->buildCancelLinkUrl(),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('new');
    // try to parse the new provided value
    try {
      $parsed_value = Yaml::decode($value);
      // Config::setData needs array for the new configuration and
      // a simple string is valid YAML for any reason.
      if (is_array($parsed_value)) {
        $form_state->setValue('parsed_value', $parsed_value);
      }
      else {
        $form_state->setErrorByName('new', $this->t('Invalid input'));
      }
    }
    catch (InvalidDataTypeException $e) {
      $form_state->setErrorByName('new', $this->t('Invalid input: %error', array('%error' => $e->getMessage())));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    try {
      $this->configFactory()->getEditable($values['name'])->setData($values['parsed_value'])->save();
      drupal_set_message($this->t('Configuration variable %variable was successfully saved.', array('%variable' => $values['name'])));
      $this->logger('devel')->info('Configuration variable %variable was successfully saved.', array('%variable' => $values['name']));

      $form_state->setRedirectUrl(Url::fromRoute('devel.configs_list'));
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      $this->logger('devel')->error('Error saving configuration variable %variable : %error.', array('%variable' => $values['name'], '%error' => $e->getMessage()));
    }
  }

  /**
   * Builds the cancel link url for the form.
   *
   * @return Url
   *   Cancel url
   */
  private function buildCancelLinkUrl() {
    $query = $this->getRequest()->query;

    if ($query->has('destination')) {
      $options = UrlHelper::parse($query->get('destination'));
      $url = Url::fromUri('internal:/' . $options['path'], $options);
    }
    else {
      $url = Url::fromRoute('devel.configs_list');
    }

    return $url;
  }

}
