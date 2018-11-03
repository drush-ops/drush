<?php

namespace Drupal\Core\Field;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface definition for field formatter plugins.
 *
 * @ingroup field_formatter
 */
interface FormatterInterface extends PluginSettingsInterface {

  /**
   * Returns a form to configure settings for the formatter.
   *
   * Invoked from \Drupal\field_ui\Form\EntityDisplayFormBase to allow
   * administrators to configure the formatter. The field_ui module takes care
   * of handling submitted form values.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the formatter settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state);

  /**
   * Returns a short summary for the current formatter settings.
   *
   * If an empty result is returned, a UI can still be provided to display
   * a settings form in case the formatter has configurable settings.
   *
   * @return string[]
   *   A short summary of the formatter settings.
   */
  public function settingsSummary();

  /**
   * Allows formatters to load information for field values being displayed.
   *
   * This should be used when a formatter needs to load additional information
   * from the database in order to render a field, for example a reference
   * field that displays properties of the referenced entities such as name or
   * type.
   *
   * This method operates on multiple entities. The $entities_items parameter
   * is an array keyed by entity ID. For performance reasons, information for
   * all involved entities should be loaded in a single query where possible.
   *
   * Changes or additions to field values are done by directly altering the
   * items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface[] $entities_items
   *   An array with the field values from the multiple entities being rendered.
   */
  public function prepareView(array $entities_items);

  /**
   * Builds a renderable array for a fully themed field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   (optional) The language that should be used to render the field. Defaults
   *   to the current content language.
   *
   * @return array
   *   A renderable array for a themed field with its label and all its values.
   */
  public function view(FieldItemListInterface $items, $langcode = NULL);

  /**
   * Builds a renderable array for a field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  public function viewElements(FieldItemListInterface $items, $langcode);

  /**
   * Returns if the formatter can be used for the provided field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition that should be checked.
   *
   * @return bool
   *   TRUE if the formatter can be used, FALSE otherwise.
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition);

}
