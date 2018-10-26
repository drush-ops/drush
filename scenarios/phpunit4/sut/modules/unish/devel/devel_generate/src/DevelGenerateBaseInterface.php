<?php

namespace Drupal\devel_generate;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base interface definition for "DevelGenerate" plugins.
 *
 * This interface details base wrapping methods that most DevelGenerate implementations
 * will want to directly inherit from Drupal\devel_generate\DevelGenerateBase.
 *
 * DevelGenerate impementationa plugins should developing settingsForm() and generateElements()
 * to achieve its own behaviour.
 *
 */
interface DevelGenerateBaseInterface extends PluginInspectionInterface {

  /**
   * Returns the array of settings, including defaults for missing settings.
   *
   * @return array
   *   The array of settings.
   */
  function getSetting($key);

  /**
   * Returns the default settings for the plugin.
   *
   * @return array
   *   The array of default setting values, keyed by setting names.
   */
  function getDefaultSettings();

  /**
   * Returns the current settings for the plugin.
   *
   * @return array
   *   The array of current setting values, keyed by setting names.
   */
  function getSettings();

  /**
   * Returns the form for the plugin.
   *
   * @return array
   *   The array of default setting values, keyed by setting names.
   */
  function settingsForm(array $form, FormStateInterface $form_state);

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  function settingsFormValidate(array $form, FormStateInterface $form_state);

  /**
   * Execute the instructions in common for all DevelGenerate plugin
   *
   * @param array $values
   *   The input values from the settings form.
   */
  function generate(array $values);

  /**
   * Responsible for validating Drush params.
   *
   * @Return an array of values ready to be used for generateElements()
   */
  function validateDrushParams($args);

}
