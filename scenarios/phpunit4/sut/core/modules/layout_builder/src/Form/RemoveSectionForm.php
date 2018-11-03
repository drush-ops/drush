<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to confirm the removal of a section.
 *
 * @internal
 */
class RemoveSectionForm extends LayoutRebuildConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_remove_section';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove this section?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  protected function handleSectionStorage(SectionStorageInterface $section_storage, FormStateInterface $form_state) {
    $section_storage->removeSection($this->delta);
  }

}
