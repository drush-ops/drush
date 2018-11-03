<?php

namespace Drupal\database_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Form controller for database_test module.
 *
 * @internal
 */
class DatabaseTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'database_test_theme_tablesort';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $header = [
      'username' => ['data' => t('Username'), 'field' => 'u.name'],
      'status' => ['data' => t('Status'), 'field' => 'u.status'],
    ];

    $query = db_select('users_field_data', 'u');
    $query->condition('u.uid', 0, '<>');
    $query->condition('u.default_langcode', 1);

    $count_query = clone $query;
    $count_query->addExpression('COUNT(u.uid)');

    $query = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->extend('Drupal\Core\Database\Query\TableSortExtender');
    $query
      ->fields('u', ['uid'])
      ->limit(50)
      ->orderByHeader($header)
      ->setCountQuery($count_query);
    $uids = $query
      ->execute()
      ->fetchCol();

    $options = [];

    foreach (User::loadMultiple($uids) as $account) {
      $options[$account->id()] = [
        'title' => ['data' => ['#title' => $account->getUsername()]],
        'username' => $account->getUsername(),
        'status' => $account->isActive() ? t('active') : t('blocked'),
      ];
    }

    $form['accounts'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No people available.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
