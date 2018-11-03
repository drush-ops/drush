<?php

namespace Drupal\shortcut;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the shortcut set entity edit forms.
 *
 * @internal
 */
class ShortcutSetForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => t('Set name'),
      '#description' => t('The new set is created by copying items from your default shortcut set.'),
      '#required' => TRUE,
      '#default_value' => $entity->label(),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => '\Drupal\shortcut\Entity\ShortcutSet::load',
        'source' => ['label'],
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      '#default_value' => $entity->id(),
      // This id could be used for menu name.
      '#maxlength' => 23,
    ];

    $form['actions']['submit']['#value'] = t('Create new set');

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $is_new = !$entity->getOriginalId();
    $entity->save();

    if ($is_new) {
      $this->messenger()->addStatus($this->t('The %set_name shortcut set has been created. You can edit it from this page.', ['%set_name' => $entity->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated set name to %set-name.', ['%set-name' => $entity->label()]));
    }
    $form_state->setRedirectUrl($this->entity->urlInfo('customize-form'));
  }

}
