<?php

namespace Drupal\Tests\config_translation\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for altering configuration translation forms.
 *
 * @group config_translation
 */
class ConfigTranslationFormTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['config_translation', 'config_translation_test', 'editor'];

  /**
   * The plugin ID of the mapper to test.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The language code of the language to use for testing.
   *
   * @var string
   */
  protected $langcode;

  protected function setUp() {
    parent::setUp();

    $definitions = \Drupal::service('plugin.manager.config_translation.mapper')->getDefinitions();
    $this->pluginId = key($definitions);

    $this->langcode = 'xx';
    ConfigurableLanguage::create(['id' => $this->langcode, 'label' => 'XX'])->save();

    \Drupal::state()->set('config_translation_test_alter_form_alter', TRUE);
  }

  /**
   * Tests altering of the configuration translation forms.
   */
  public function testConfigTranslationFormAlter() {
    $form_builder = \Drupal::formBuilder();
    $add_form = $form_builder->getForm('Drupal\config_translation\Form\ConfigTranslationAddForm', \Drupal::routeMatch(), $this->pluginId, $this->langcode);
    $edit_form = $form_builder->getForm('Drupal\config_translation\Form\ConfigTranslationEditForm', \Drupal::routeMatch(), $this->pluginId, $this->langcode);

    // Test that hook_form_BASE_FORM_ID_alter() was called for the base form ID
    // 'config_translation_form'.
    $this->assertTrue($add_form['#base_altered']);
    $this->assertTrue($edit_form['#base_altered']);

    // Test that hook_form_FORM_ID_alter() was called for the form IDs
    // 'config_translation_add_form' and 'config_translation_edit_form'.
    $this->assertTrue($add_form['#altered']);
    $this->assertTrue($edit_form['#altered']);
  }

}
