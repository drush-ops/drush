<?php

namespace Drupal\editor_test\Plugin\Editor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;
use Drupal\editor\Plugin\EditorBase;

/**
 * Defines a Unicorn-powered text editor for Drupal (for testing purposes).
 *
 * @Editor(
 *   id = "unicorn",
 *   label = @Translation("Unicorn Editor"),
 *   supports_content_filtering = TRUE,
 *   supports_inline_editing = TRUE,
 *   is_xss_safe = FALSE,
 *   supported_element_types = {
 *     "textarea",
 *     "textfield",
 *   }
 * )
 */
class UnicornEditor extends EditorBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    return ['ponies_too' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $form['ponies_too'] = [
      '#title' => t('Pony mode'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getJSSettings(Editor $editor) {
    $js_settings = [];
    $settings = $editor->getSettings();
    if ($settings['ponies_too']) {
      $js_settings['ponyModeEnabled'] = TRUE;
    }
    return $js_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'editor_test/unicorn',
    ];
  }

}
