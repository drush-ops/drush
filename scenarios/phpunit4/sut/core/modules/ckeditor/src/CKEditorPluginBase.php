<?php

namespace Drupal\ckeditor;

use Drupal\Core\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines a base CKEditor plugin implementation.
 *
 * No other CKEditor plugins can be internal, unless a different CKEditor build
 * than the one provided by Drupal core is used. Most CKEditor plugins don't
 * need to provide additional settings forms.
 *
 * This base class assumes that your plugin has buttons that you want to be
 * enabled through the toolbar builder UI. It is still possible to also
 * implement the CKEditorPluginContextualInterface (for contextual enabling) and
 * CKEditorPluginConfigurableInterface interfaces (for configuring plugin
 * settings).
 *
 * NOTE: the Drupal plugin ID should correspond to the CKEditor plugin name.
 *
 * @see \Drupal\ckeditor\CKEditorPluginInterface
 * @see \Drupal\ckeditor\CKEditorPluginButtonsInterface
 * @see \Drupal\ckeditor\CKEditorPluginContextualInterface
 * @see \Drupal\ckeditor\CKEditorPluginConfigurableInterface
 * @see \Drupal\ckeditor\CKEditorPluginManager
 * @see \Drupal\ckeditor\Annotation\CKEditorPlugin
 * @see plugin_api
 */
abstract class CKEditorPluginBase extends PluginBase implements CKEditorPluginInterface, CKEditorPluginButtonsInterface {

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

}
