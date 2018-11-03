<?php

namespace Drupal\Tests\language\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the content translation settings language selector options.
 *
 * @group language
 */
class LanguageSelectorTranslatableTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'language',
    'content_translation',
    'node',
    'comment',
    'field_ui',
    'entity_test',
    'locale',
  ];

  /**
   * The user with administrator privileges.
   *
   * @var \Drupal\user\Entity\User
   */
  public $administrator;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create user and set permissions.
    $this->administrator = $this->drupalCreateUser($this->getAdministratorPermissions(), 'administrator');
    $this->drupalLogin($this->administrator);
  }

  /**
   * Returns an array of permissions needed for the translator.
   */
  protected function getAdministratorPermissions() {
    return array_filter(
      ['translate interface',
        'administer content translation',
        'create content translations',
        'update content translations',
        'delete content translations',
        'administer languages',
      ]
    );
  }

  /**
   * Tests content translation language selectors are correctly translated.
   */
  public function testLanguageStringSelector() {
    // Add another language.
    $edit = ['predefined_langcode' => 'es'];
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Translate the string English in Spanish (Inglés). Override config entity.
    $name_translation = 'Inglés';
    \Drupal::languageManager()
      ->getLanguageConfigOverride('es', 'language.entity.en')
      ->set('label', $name_translation)
      ->save();

    // Check content translation overview selector.
    $path = 'es/admin/config/regional/content-language';
    $this->drupalGet($path);

    // Get en language from selector.
    $elements = $this->xpath('//select[@id=:id]//option[@value=:option]', [':id' => 'edit-settings-user-user-settings-language-langcode', ':option' => 'en']);

    // Check that the language text is translated.
    $this->assertEqual($elements[0]->getText(), $name_translation, 'Checking the option string English is translated to Spanish.');
  }

}
