<?php

namespace Drupal\webprofiler\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class ProfilesFilterForm
 */
class ProfilesFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webprofiler_profiles_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP'),
      '#size' => 30,
      '#default_value' => $this->getRequest()->query->get('ip'),
      '#prefix' => '<div class="form--inline clearfix">',
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Url'),
      '#size' => 30,
      '#default_value' => $this->getRequest()->query->get('url'),
    ];

    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#options' => ['GET' => 'GET', 'POST' => 'POST'],
      '#default_value' => $this->getRequest()->query->get('method'),
    ];

    $limits = [10, 50, 100];
    $form['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit'),
      '#options' => array_combine($limits, $limits),
      '#default_value' => $this->getRequest()->query->get('limit'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => t('Filter'),
      '#attributes' => ['class' => ['button--primary']],
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $ip = $form_state->getValue('ip');// ['values']['ip'];
    $url = $form_state->getValue('url');
    $method = $form_state->getValue('method');
    $limit = $form_state->getValue('limit');

    $url = new Url('webprofiler.admin_list', [], [
      'query' => [
        'ip' => $ip,
        'url' => $url,
        'method' => $method,
        'limit' => $limit,
      ]
    ]);

    $form_state->setRedirectUrl($url);
  }
}
