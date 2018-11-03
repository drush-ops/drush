<?php

namespace Drupal\book\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure book settings for this site.
 *
 * @internal
 */
class BookSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'book_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['book.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $types = node_type_get_names();
    $config = $this->config('book.settings');
    $form['book_allowed_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types allowed in book outlines'),
      '#default_value' => $config->get('allowed_types'),
      '#options' => $types,
      '#description' => $this->t('Users with the %outline-perm permission can add all content types.', ['%outline-perm' => $this->t('Administer book outlines')]),
      '#required' => TRUE,
    ];
    $form['book_child_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Content type for the <em>Add child page</em> link'),
      '#default_value' => $config->get('child_type'),
      '#options' => $types,
      '#required' => TRUE,
    ];
    $form['array_filter'] = ['#type' => 'value', '#value' => TRUE];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $child_type = $form_state->getValue('book_child_type');
    if ($form_state->isValueEmpty(['book_allowed_types', $child_type])) {
      $form_state->setErrorByName('book_child_type', $this->t('The content type for the %add-child link must be one of those selected as an allowed book outline type.', ['%add-child' => $this->t('Add child page')]));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $allowed_types = array_filter($form_state->getValue('book_allowed_types'));
    // We need to save the allowed types in an array ordered by machine_name so
    // that we can save them in the correct order if node type changes.
    // @see book_node_type_update().
    sort($allowed_types);
    $this->config('book.settings')
    // Remove unchecked types.
      ->set('allowed_types', $allowed_types)
      ->set('child_type', $form_state->getValue('book_child_type'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
