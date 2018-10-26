<?php

namespace Drupal\devel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that allows privileged users to execute arbitrary PHP code.
 */
class ExecutePHP extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devel_execute_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array(
      '#title' => $this->t('Execute PHP Code'),
      '#description' => $this->t('Execute some PHP code'),
    );
    $form['execute']['code'] = array(
      '#type' => 'textarea',
      '#title' => t('PHP code to execute'),
      '#description' => t('Enter some code. Do not use <code>&lt;?php ?&gt;</code> tags.'),
      '#default_value' => (isset($_SESSION['devel_execute_code']) ? $_SESSION['devel_execute_code'] : ''),
      '#rows' => 20,
    );
    $form['execute']['op'] = array('#type' => 'submit', '#value' => t('Execute'));
    $form['#redirect'] = FALSE;
    if (isset($_SESSION['devel_execute_code'])) {
      unset($_SESSION['devel_execute_code']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ob_start();
    $code = $form_state->getValue('code');
    print eval($code);
    $_SESSION['devel_execute_code'] = $code;
    dpm(ob_get_clean());
  }

}
