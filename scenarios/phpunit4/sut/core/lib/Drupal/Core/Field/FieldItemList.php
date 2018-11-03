<?php

namespace Drupal\Core\Field;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;

/**
 * Represents an entity field; that is, a list of field item objects.
 *
 * An entity field is a list of field items, each containing a set of
 * properties. Note that even single-valued entity fields are represented as
 * list of field items, however for easy access to the contained item the entity
 * field delegates __get() and __set() calls directly to the first item.
 */
class FieldItemList extends ItemList implements FieldItemListInterface {

  /**
   * Numerically indexed array of field items.
   *
   * @var \Drupal\Core\Field\FieldItemInterface[]
   */
  protected $list = [];

  /**
   * The langcode of the field values held in the object.
   *
   * @var string
   */
  protected $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;

  /**
   * {@inheritdoc}
   */
  protected function createItem($offset = 0, $value = NULL) {
    return \Drupal::service('plugin.manager.field.field_type')->createFieldItem($this, $offset, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    // The "parent" is the TypedData object for the entity, we need to unwrap
    // the actual entity.
    return $this->getParent()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setLangcode($langcode) {
    $this->langcode = $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return $this->definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->definition->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($setting_name) {
    return $this->definition->getSetting($setting_name);
  }

  /**
   * {@inheritdoc}
   */
  public function filterEmptyItems() {
    $this->filter(function ($item) {
      return !$item->isEmpty();
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // Support passing in only the value of the first item, either as a literal
    // (value of the first property) or as an array of properties.
    if (isset($values) && (!is_array($values) || (!empty($values) && !is_numeric(current(array_keys($values)))))) {
      $values = [0 => $values];
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function __get($property_name) {
    // For empty fields, $entity->field->property is NULL.
    if ($item = $this->first()) {
      return $item->__get($property_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __set($property_name, $value) {
    // For empty fields, $entity->field->property = $value automatically
    // creates the item before assigning the value.
    $item = $this->first() ?: $this->appendItem();
    $item->__set($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function __isset($property_name) {
    if ($item = $this->first()) {
      return $item->__isset($property_name);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function __unset($property_name) {
    if ($item = $this->first()) {
      $item->__unset($property_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_control_handler = \Drupal::entityManager()->getAccessControlHandler($this->getEntity()->getEntityTypeId());
    return $access_control_handler->fieldAccess($operation, $this->getFieldDefinition(), $account, $this, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultAccess($operation = 'view', AccountInterface $account = NULL) {
    // Grant access per default.
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    if ($value = $this->getFieldDefinition()->getDefaultValue($this->getEntity())) {
      $this->setValue($value, $notify);
    }
    else {
      // Create one field item and give it a chance to apply its defaults.
      // Remove it if this ended up doing nothing.
      // @todo Having to create an item in case it wants to set a value is
      // absurd. Remove that in https://www.drupal.org/node/2356623.
      $item = $this->first() ?: $this->appendItem();
      $item->applyDefaultValue(FALSE);
      $this->filterEmptyItems();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    // Filter out empty items.
    $this->filterEmptyItems();

    $this->delegateMethod('preSave');
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $result = $this->delegateMethod('postSave', $update);
    return (bool) array_filter($result);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->delegateMethod('delete');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    $this->delegateMethod('deleteRevision');
  }

  /**
   * Calls a method on each FieldItem.
   *
   * Any argument passed will be forwarded to the invoked method.
   *
   * @param string $method
   *   The name of the method to be invoked.
   *
   * @return array
   *   An array of results keyed by delta.
   */
  protected function delegateMethod($method) {
    $result = [];
    $args = array_slice(func_get_args(), 1);
    foreach ($this->list as $delta => $item) {
      // call_user_func_array() is way slower than a direct call so we avoid
      // using it if have no parameters.
      $result[$delta] = $args ? call_user_func_array([$item, $method], $args) : $item->{$method}();
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function view($display_options = []) {
    $view_builder = \Drupal::entityManager()->getViewBuilder($this->getEntity()->getEntityTypeId());
    return $view_builder->viewField($this, $display_options);
  }

  /**
   * {@inheritdoc}
   */
  public function generateSampleItems($count = 1) {
    $field_definition = $this->getFieldDefinition();
    $field_type_class = $field_definition->getItemDefinition()->getClass();
    for ($delta = 0; $delta < $count; $delta++) {
      $values[$delta] = $field_type_class::generateSampleValue($field_definition);
    }
    $this->setValue($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    // Check that the number of values doesn't exceed the field cardinality. For
    // form submitted values, this can only happen with 'multiple value'
    // widgets.
    $cardinality = $this->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    if ($cardinality != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $constraints[] = $this->getTypedDataManager()
        ->getValidationConstraintManager()
        ->create('Count', [
          'max' => $cardinality,
          'maxMessage' => t('%name: this field cannot hold more than @count values.', ['%name' => $this->getFieldDefinition()->getLabel(), '@count' => $cardinality]),
        ]);
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {
    if (empty($this->getFieldDefinition()->getDefaultValueCallback())) {
      if ($widget = $this->defaultValueWidget($form_state)) {
        // Place the input in a separate place in the submitted values tree.
        $element = ['#parents' => ['default_value_input']];
        $element += $widget->form($this, $element, $form_state);

        return $element;
      }
      else {
        return ['#markup' => $this->t('No widget available for: %type.', ['%type' => $this->getFieldDefinition()->getType()])];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {
    // Extract the submitted value, and validate it.
    if ($widget = $this->defaultValueWidget($form_state)) {
      $widget->extractFormValues($this, $element, $form_state);
      // Force a non-required field definition.
      // @see self::defaultValueWidget().
      $this->getFieldDefinition()->setRequired(FALSE);
      $violations = $this->validate();

      // Assign reported errors to the correct form element.
      if (count($violations)) {
        $widget->flagErrors($this, $violations, $element, $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    // Extract the submitted value, and return it as an array.
    if ($widget = $this->defaultValueWidget($form_state)) {
      $widget->extractFormValues($this, $element, $form_state);
      return $this->getValue();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    return $default_value;
  }

  /**
   * Returns the widget object used in default value form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return \Drupal\Core\Field\WidgetInterface|null
   *   A Widget object or NULL if no widget is available.
   */
  protected function defaultValueWidget(FormStateInterface $form_state) {
    if (!$form_state->has('default_value_widget')) {
      $entity = $this->getEntity();

      // Force a non-required widget.
      $definition = $this->getFieldDefinition();
      $definition->setRequired(FALSE);
      $definition->setDescription('');

      // Use the widget currently configured for the 'default' form mode, or
      // fallback to the default widget for the field type.
      $entity_form_display = entity_get_form_display($entity->getEntityTypeId(), $entity->bundle(), 'default');
      $widget = $entity_form_display->getRenderer($this->getFieldDefinition()->getName());
      if (!$widget) {
        $widget = \Drupal::service('plugin.manager.field.widget')->getInstance(['field_definition' => $this->getFieldDefinition()]);
      }

      $form_state->set('default_value_widget', $widget);
    }

    return $form_state->get('default_value_widget');
  }

  /**
   * {@inheritdoc}
   */
  public function equals(FieldItemListInterface $list_to_compare) {
    $count1 = count($this);
    $count2 = count($list_to_compare);
    if ($count1 === 0 && $count2 === 0) {
      // Both are empty we can safely assume that it did not change.
      return TRUE;
    }
    if ($count1 !== $count2) {
      // One of them is empty but not the other one so the value changed.
      return FALSE;
    }
    $value1 = $this->getValue();
    $value2 = $list_to_compare->getValue();
    if ($value1 === $value2) {
      return TRUE;
    }
    // If the values are not equal ensure a consistent order of field item
    // properties and remove properties which will not be saved.
    $property_definitions = $this->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();
    $non_computed_properties = array_filter($property_definitions, function (DataDefinitionInterface $property) {
      return !$property->isComputed();
    });
    $callback = function (&$value) use ($non_computed_properties) {
      if (is_array($value)) {
        $value = array_intersect_key($value, $non_computed_properties);
        ksort($value);
      }
    };
    array_walk($value1, $callback);
    array_walk($value2, $callback);

    return $value1 == $value2;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAffectingChanges(FieldItemListInterface $original_items, $langcode) {
    return !$this->equals($original_items);
  }

}
