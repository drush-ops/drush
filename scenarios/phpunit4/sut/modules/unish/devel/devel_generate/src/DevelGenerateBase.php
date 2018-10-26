<?php

namespace Drupal\devel_generate;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a base DevelGenerate plugin implementation.
 */
abstract class DevelGenerateBase extends PluginBase implements DevelGenerateBaseInterface {

  /**
   * The plugin settings.
   *
   * @var array
   */
  protected $settings = array();

  /**
   * The random data generator.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * {@inheritdoc}
   */
  public function getSetting($key) {
    // Merge defaults if we have no value for the key.
    if (!array_key_exists($key, $this->settings)) {
      $this->settings = $this->getDefaultSettings();
    }
    return isset($this->settings[$key]) ? $this->settings[$key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    $definition = $this->getPluginDefinition();
    return $definition['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  function settingsFormValidate(array $form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function generate(array $values) {
    $this->generateElements($values);
    $this->setMessage('Generate process complete.');
  }

  /**
   * Business logic relating with each DevelGenerate plugin
   *
   * @param array $values
   *   The input values from the settings form.
   */
  protected function generateElements(array $values) {

  }

  /**
   * Populate the fields on a given entity with sample values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be enriched with sample field values.
   */
  public static function populateFields(EntityInterface $entity) {
    /** @var \Drupal\field\FieldConfigInterface[] $instances */
    $instances = entity_load_multiple_by_properties('field_config', array('entity_type' => $entity->getEntityType()->id(), 'bundle' => $entity->bundle()));

    if ($skips = function_exists('drush_get_option') ? drush_get_option('skip-fields', '') : @$_REQUEST['skip-fields']) {
      foreach (explode(',', $skips) as $skip) {
        unset($instances[$skip]);
      }
    }

    foreach ($instances as $instance) {
      $field_storage = $instance->getFieldStorageDefinition();
      $max = $cardinality = $field_storage->getCardinality();
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        // Just an arbitrary number for 'unlimited'
        $max = rand(1, 3);
      }
      $field_name = $field_storage->getName();
      $entity->$field_name->generateSampleItems($max);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleDrushParams($args) {

  }

  /**
   * Set a message for either drush or the web interface.
   *
   * @param string $msg
   *   The message to display.
   * @param string $type
   *   (optional) The message type, as defined by drupal_set_message(). Defaults
   *   to 'status'
   */
  protected function setMessage($msg, $type = 'status') {
    $function = 'drupal_set_message';
    if (function_exists('drush_log')) {
      $function = 'drush_log';
      $msg = strip_tags($msg);
    }
    $function($msg, $type);
  }

  /**
   * Check if a given param is a number.
   *
   * @param mixed $number
   *   The parameter to check.
   *
   * @return bool
   *   TRUE if the parameter is a number, FALSE otherwise.
   */
  public static function isNumber($number) {
    if ($number == NULL) return FALSE;
    if (!is_numeric($number)) return FALSE;
    return TRUE;
  }

  /**
   * Returns the random data generator.
   *
   * @return \Drupal\Component\Utility\Random
   *   The random data generator.
   */
  protected function getRandom() {
    if (!$this->random) {
      $this->random = new Random();
    }
    return $this->random;
  }

  protected function isDrush8() {
    return function_exists('drush_drupal_load_autoloader');
  }
}
