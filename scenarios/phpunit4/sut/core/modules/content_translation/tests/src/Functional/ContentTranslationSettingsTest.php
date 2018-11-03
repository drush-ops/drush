<?php

namespace Drupal\Tests\content_translation\Functional;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Language\Language;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the content translation settings UI.
 *
 * @group content_translation
 */
class ContentTranslationSettingsTest extends BrowserTestBase {

  use CommentTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['language', 'content_translation', 'node', 'comment', 'field_ui', 'entity_test'];

  protected function setUp() {
    parent::setUp();

    // Set up two content types to test fields shared between different
    // bundles.
    $this->drupalCreateContentType(['type' => 'article']);
    $this->drupalCreateContentType(['type' => 'page']);
    $this->addDefaultCommentField('node', 'article', 'comment_article', CommentItemInterface::OPEN, 'comment_article');
    $this->addDefaultCommentField('node', 'page', 'comment_page');

    $admin_user = $this->drupalCreateUser(['access administration pages', 'administer languages', 'administer content translation', 'administer content types', 'administer node fields', 'administer comment fields', 'administer comments', 'administer comment types', 'administer account settings']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that the settings UI works as expected.
   */
  public function testSettingsUI() {
    // Check for the content_translation_menu_links_discovered_alter() changes.
    $this->drupalGet('admin/config');
    $this->assertLink('Content language and translation');
    $this->assertText('Configure language and translation support for content.');
    // Test that the translation settings are ignored if the bundle is marked
    // translatable but the entity type is not.
    $edit = ['settings[comment][comment_article][translatable]' => TRUE];
    $this->assertSettings('comment', NULL, FALSE, $edit);

    // Test that the translation settings are ignored if only a field is marked
    // as translatable and not the related entity type and bundle.
    $edit = ['settings[comment][comment_article][fields][comment_body]' => TRUE];
    $this->assertSettings('comment', NULL, FALSE, $edit);

    // Test that the translation settings are not stored if an entity type and
    // bundle are marked as translatable but no field is.
    $edit = [
      'entity_types[comment]' => TRUE,
      'settings[comment][comment_article][translatable]' => TRUE,
      // Base fields are translatable by default.
      'settings[comment][comment_article][fields][changed]' => FALSE,
      'settings[comment][comment_article][fields][created]' => FALSE,
      'settings[comment][comment_article][fields][homepage]' => FALSE,
      'settings[comment][comment_article][fields][hostname]' => FALSE,
      'settings[comment][comment_article][fields][mail]' => FALSE,
      'settings[comment][comment_article][fields][name]' => FALSE,
      'settings[comment][comment_article][fields][status]' => FALSE,
      'settings[comment][comment_article][fields][subject]' => FALSE,
      'settings[comment][comment_article][fields][uid]' => FALSE,
    ];
    $this->assertSettings('comment', 'comment_article', FALSE, $edit);
    $xpath_err = '//div[contains(@class, "error")]';
    $this->assertTrue($this->xpath($xpath_err), 'Enabling translation only for entity bundles generates a form error.');

    // Test that the translation settings are not stored if a non-configurable
    // language is set as default and the language selector is hidden.
    $edit = [
      'entity_types[comment]' => TRUE,
      'settings[comment][comment_article][settings][language][langcode]' => Language::LANGCODE_NOT_SPECIFIED,
      'settings[comment][comment_article][settings][language][language_alterable]' => FALSE,
      'settings[comment][comment_article][translatable]' => TRUE,
      'settings[comment][comment_article][fields][comment_body]' => TRUE,
    ];
    $this->assertSettings('comment', 'comment_article', FALSE, $edit);
    $this->assertTrue($this->xpath($xpath_err), 'Enabling translation with a fixed non-configurable language generates a form error.');

    // Test that a field shared among different bundles can be enabled without
    // needing to make all the related bundles translatable.
    $edit = [
      'entity_types[comment]' => TRUE,
      'settings[comment][comment_article][settings][language][langcode]' => 'current_interface',
      'settings[comment][comment_article][settings][language][language_alterable]' => TRUE,
      'settings[comment][comment_article][translatable]' => TRUE,
      'settings[comment][comment_article][fields][comment_body]' => TRUE,
      // Override both comment subject fields to untranslatable.
      'settings[comment][comment_article][fields][subject]' => FALSE,
      'settings[comment][comment][fields][subject]' => FALSE,
    ];
    $this->assertSettings('comment', 'comment_article', TRUE, $edit);
    $definition = $this->entityManager()->getFieldDefinitions('comment', 'comment_article')['comment_body'];
    $this->assertTrue($definition->isTranslatable(), 'Article comment body is translatable.');
    $definition = $this->entityManager()->getFieldDefinitions('comment', 'comment_article')['subject'];
    $this->assertFalse($definition->isTranslatable(), 'Article comment subject is not translatable.');

    $definition = $this->entityManager()->getFieldDefinitions('comment', 'comment')['comment_body'];
    $this->assertFalse($definition->isTranslatable(), 'Page comment body is not translatable.');
    $definition = $this->entityManager()->getFieldDefinitions('comment', 'comment')['subject'];
    $this->assertFalse($definition->isTranslatable(), 'Page comment subject is not translatable.');

    // Test that translation can be enabled for base fields.
    $edit = [
      'entity_types[entity_test_mul]' => TRUE,
      'settings[entity_test_mul][entity_test_mul][translatable]' => TRUE,
      'settings[entity_test_mul][entity_test_mul][fields][name]' => TRUE,
      'settings[entity_test_mul][entity_test_mul][fields][user_id]' => FALSE,
    ];
    $this->assertSettings('entity_test_mul', 'entity_test_mul', TRUE, $edit);
    $field_override = BaseFieldOverride::loadByName('entity_test_mul', 'entity_test_mul', 'name');
    $this->assertTrue($field_override->isTranslatable(), 'Base fields can be overridden with a base field bundle override entity.');
    $definitions = $this->entityManager()->getFieldDefinitions('entity_test_mul', 'entity_test_mul');
    $this->assertTrue($definitions['name']->isTranslatable() && !$definitions['user_id']->isTranslatable(), 'Base field bundle overrides were correctly altered.');

    // Test that language settings are correctly stored.
    $language_configuration = ContentLanguageSettings::loadByEntityTypeBundle('comment', 'comment_article');
    $this->assertEqual($language_configuration->getDefaultLangcode(), 'current_interface', 'The default language for article comments is set to the interface text language selected for page.');
    $this->assertTrue($language_configuration->isLanguageAlterable(), 'The language selector for article comments is shown.');

    // Verify language widget appears on comment type form.
    $this->drupalGet('admin/structure/comment/manage/comment_article');
    $this->assertField('language_configuration[content_translation]');
    $this->assertFieldChecked('edit-language-configuration-content-translation');

    // Verify that translation may be enabled for the article content type.
    $edit = [
      'language_configuration[content_translation]' => TRUE,
    ];
    // Make sure the checkbox is available and not checked by default.
    $this->drupalGet('admin/structure/types/manage/article');
    $this->assertField('language_configuration[content_translation]');
    $this->assertNoFieldChecked('edit-language-configuration-content-translation');
    $this->drupalPostForm('admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->drupalGet('admin/structure/types/manage/article');
    $this->assertFieldChecked('edit-language-configuration-content-translation');

    // Test that the title field of nodes is available in the settings form.
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][article][settings][language][langcode]' => 'current_interface',
      'settings[node][article][settings][language][language_alterable]' => TRUE,
      'settings[node][article][translatable]' => TRUE,
      'settings[node][article][fields][title]' => TRUE,
    ];
    $this->assertSettings('node', NULL, TRUE, $edit);

    foreach ([TRUE, FALSE] as $translatable) {
      // Test that configurable field translatability is correctly switched.
      $edit = ['settings[node][article][fields][body]' => $translatable];
      $this->assertSettings('node', 'article', TRUE, $edit);
      $field = FieldConfig::loadByName('node', 'article', 'body');
      $definitions = \Drupal::entityManager()->getFieldDefinitions('node', 'article');
      $this->assertEqual($definitions['body']->isTranslatable(), $translatable, 'Field translatability correctly switched.');
      $this->assertEqual($field->isTranslatable(), $definitions['body']->isTranslatable(), 'Configurable field translatability correctly switched.');

      // Test that also the Field UI form behaves correctly.
      $translatable = !$translatable;
      $edit = ['translatable' => $translatable];
      $this->drupalPostForm('admin/structure/types/manage/article/fields/node.article.body', $edit, t('Save settings'));
      \Drupal::entityManager()->clearCachedFieldDefinitions();
      $field = FieldConfig::loadByName('node', 'article', 'body');
      $definitions = \Drupal::entityManager()->getFieldDefinitions('node', 'article');
      $this->assertEqual($definitions['body']->isTranslatable(), $translatable, 'Field translatability correctly switched.');
      $this->assertEqual($field->isTranslatable(), $definitions['body']->isTranslatable(), 'Configurable field translatability correctly switched.');
    }

    // Test that the order of the language list is similar to other language
    // lists, such as in Views UI.
    $this->drupalGet('admin/config/regional/content-language');

    $expected_elements = [
      'site_default',
      'current_interface',
      'authors_default',
      'en',
      'und',
      'zxx',
    ];
    $elements = $this->xpath('//select[@id="edit-settings-node-article-settings-language-langcode"]/option');
    // Compare values inside the option elements with expected values.
    for ($i = 0; $i < count($elements); $i++) {
      $this->assertEqual($elements[$i]->getValue(), $expected_elements[$i]);
    }
  }

  /**
   * Tests the language settings checkbox on account settings page.
   */
  public function testAccountLanguageSettingsUI() {
    // Make sure the checkbox is available and not checked by default.
    $this->drupalGet('admin/config/people/accounts');
    $this->assertField('language[content_translation]');
    $this->assertNoFieldChecked('edit-language-content-translation');

    $edit = [
      'language[content_translation]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/people/accounts', $edit, t('Save configuration'));
    $this->drupalGet('admin/config/people/accounts');
    $this->assertFieldChecked('edit-language-content-translation');

    // Make sure account settings can be saved.
    $this->drupalPostForm('admin/config/people/accounts', ['anonymous' => 'Save me please!'], 'Save configuration');
    $this->assertFieldByName('anonymous', 'Save me please!', 'Anonymous name has been changed.');
    $this->assertText('The configuration options have been saved.');
  }

  /**
   * Asserts that translatability has the expected value for the given bundle.
   *
   * @param string $entity_type
   *   The entity type for which to check translatability.
   * @param string $bundle
   *   The bundle for which to check translatability.
   * @param bool $enabled
   *   TRUE if translatability should be enabled, FALSE otherwise.
   * @param array $edit
   *   An array of values to submit to the content translation settings page.
   *
   * @return bool
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertSettings($entity_type, $bundle, $enabled, $edit) {
    $this->drupalPostForm('admin/config/regional/content-language', $edit, t('Save configuration'));
    $args = ['@entity_type' => $entity_type, '@bundle' => $bundle, '@enabled' => $enabled ? 'enabled' : 'disabled'];
    $message = format_string('Translation for entity @entity_type (@bundle) is @enabled.', $args);
    \Drupal::entityManager()->clearCachedDefinitions();
    return $this->assertEqual(\Drupal::service('content_translation.manager')->isEnabled($entity_type, $bundle), $enabled, $message);
  }

  /**
   * Tests that field setting depends on bundle translatability.
   */
  public function testFieldTranslatableSettingsUI() {
    // At least one field needs to be translatable to enable article for
    // translation. Create an extra field to be used for this purpose. We use
    // the UI to test our form alterations.
    $edit = [
      'new_storage_type' => 'text',
      'label' => 'Test',
      'field_name' => 'article_text',
    ];
    $this->drupalPostForm('admin/structure/types/manage/article/fields/add-field', $edit, 'Save and continue');

    // Tests that field doesn't have translatable setting if bundle is not
    // translatable.
    $path = 'admin/structure/types/manage/article/fields/node.article.field_article_text';
    $this->drupalGet($path);
    $this->assertFieldByXPath('//input[@id="edit-translatable" and @disabled="disabled"]');
    $this->assertText('To configure translation for this field, enable language support for this type.', 'No translatable setting for field.');

    // Tests that field has translatable setting if bundle is translatable.
    // Note: this field is not translatable when enable bundle translatability.
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][article][settings][language][language_alterable]' => TRUE,
      'settings[node][article][translatable]' => TRUE,
      'settings[node][article][fields][field_article_text]' => TRUE,
    ];
    $this->assertSettings('node', 'article', TRUE, $edit);
    $this->drupalGet($path);
    $this->assertFieldByXPath('//input[@id="edit-translatable" and not(@disabled) and @checked="checked"]');
    $this->assertNoText('To enable translation of this field, enable language support for this type.', 'Translatable setting for field available.');
  }

  /**
   * Tests the translatable settings checkbox for untranslatable entities.
   */
  public function testNonTranslatableTranslationSettingsUI() {
    $this->drupalGet('admin/config/regional/content-language');
    $this->assertNoField('settings[entity_test][entity_test][translatable]');
  }

  /**
   * Returns the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager;
   */
  protected function entityManager() {
    return $this->container->get('entity.manager');
  }

}
