<?php

namespace Drupal\Tests\node\Functional;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests multilingual support for fields.
 *
 * @group node
 */
class NodeFieldMultilingualTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'language'];

  protected function setUp() {
    parent::setUp();

    // Create Basic page node type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    // Setup users.
    $admin_user = $this->drupalCreateUser(['administer languages', 'administer content types', 'access administration pages', 'create page content', 'edit own page content']);
    $this->drupalLogin($admin_user);

    // Add a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => '1'];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Set "Basic page" content type to use multilingual support.
    $edit = [
      'language_configuration[language_alterable]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/types/manage/page', $edit, t('Save content type'));
    $this->assertRaw(t('The content type %type has been updated.', ['%type' => 'Basic page']), 'Basic page content type has been updated.');

    // Make node body translatable.
    $field_storage = FieldStorageConfig::loadByName('node', 'body');
    $field_storage->setTranslatable(TRUE);
    $field_storage->save();
  }

  /**
   * Tests whether field languages are correctly set through the node form.
   */
  public function testMultilingualNodeForm() {
    // Create "Basic page" content.
    $langcode = language_get_default_langcode('node', 'page');
    $title_key = 'title[0][value]';
    $title_value = $this->randomMachineName(8);
    $body_key = 'body[0][value]';
    $body_value = $this->randomMachineName(16);

    // Create node to edit.
    $edit = [];
    $edit[$title_key] = $title_value;
    $edit[$body_key] = $body_value;
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    // Check that the node exists in the database.
    $node = $this->drupalGetNodeByTitle($edit[$title_key]);
    $this->assertTrue($node, 'Node found in database.');
    $this->assertTrue($node->language()->getId() == $langcode && $node->body->value == $body_value, 'Field language correctly set.');

    // Change node language.
    $langcode = 'it';
    $this->drupalGet("node/{$node->id()}/edit");
    $edit = [
      $title_key => $this->randomMachineName(8),
      'langcode[0][value]' => $langcode,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($edit[$title_key], TRUE);
    $this->assertTrue($node, 'Node found in database.');
    $this->assertTrue($node->language()->getId() == $langcode && $node->body->value == $body_value, 'Field language correctly changed.');

    // Enable content language URL detection.
    $this->container->get('language_negotiator')->saveConfiguration(LanguageInterface::TYPE_CONTENT, [LanguageNegotiationUrl::METHOD_ID => 0]);

    // Test multilingual field language fallback logic.
    $this->drupalGet("it/node/{$node->id()}");
    $this->assertRaw($body_value, 'Body correctly displayed using Italian as requested language');

    $this->drupalGet("node/{$node->id()}");
    $this->assertRaw($body_value, 'Body correctly displayed using English as requested language');
  }

  /**
   * Tests multilingual field display settings.
   */
  public function testMultilingualDisplaySettings() {
    // Create "Basic page" content.
    $title_key = 'title[0][value]';
    $title_value = $this->randomMachineName(8);
    $body_key = 'body[0][value]';
    $body_value = $this->randomMachineName(16);

    // Create node to edit.
    $edit = [];
    $edit[$title_key] = $title_value;
    $edit[$body_key] = $body_value;
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    // Check that the node exists in the database.
    $node = $this->drupalGetNodeByTitle($edit[$title_key]);
    $this->assertTrue($node, 'Node found in database.');

    // Check if node body is showed.
    $this->drupalGet('node/' . $node->id());
    $body = $this->xpath('//article[contains(concat(" ", normalize-space(@class), " "), :node-class)]//div[contains(concat(" ", normalize-space(@class), " "), :content-class)]/descendant::p', [
      ':node-class' => ' node ',
      ':content-class' => 'node__content',
    ]);
    $this->assertEqual($body[0]->getText(), $node->body->value, 'Node body found.');
  }

}
