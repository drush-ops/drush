<?php

namespace Drupal\alinks\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Keyword edit forms.
 *
 * @ingroup alinks
 */
class KeywordForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Keyword.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Keyword.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.alink_keyword.collection');
  }

}
