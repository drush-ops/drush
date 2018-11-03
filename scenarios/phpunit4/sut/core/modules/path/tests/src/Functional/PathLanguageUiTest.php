<?php

namespace Drupal\Tests\path\Functional;

/**
 * Confirm that the Path module user interface works with languages.
 *
 * @group path
 */
class PathLanguageUiTest extends PathTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['path', 'locale', 'locale_test'];

  protected function setUp() {
    parent::setUp();

    // Create and log in user.
    $web_user = $this->drupalCreateUser(['edit any page content', 'create page content', 'administer url aliases', 'create url aliases', 'administer languages', 'access administration pages']);
    $this->drupalLogin($web_user);

    // Enable French language.
    $edit = [];
    $edit['predefined_langcode'] = 'fr';

    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => 1];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));
  }

  /**
   * Tests that a language-neutral URL alias works.
   */
  public function testLanguageNeutralUrl() {
    $name = $this->randomMachineName(8);
    $edit = [];
    $edit['source'] = '/admin/config/search/path';
    $edit['alias'] = '/' . $name;
    $this->drupalPostForm('admin/config/search/path/add', $edit, t('Save'));

    $this->drupalGet($name);
    $this->assertText(t('Filter aliases'), 'Language-neutral URL alias works');
  }

  /**
   * Tests that a default language URL alias works.
   */
  public function testDefaultLanguageUrl() {
    $name = $this->randomMachineName(8);
    $edit = [];
    $edit['source'] = '/admin/config/search/path';
    $edit['alias'] = '/' . $name;
    $edit['langcode'] = 'en';
    $this->drupalPostForm('admin/config/search/path/add', $edit, t('Save'));

    $this->drupalGet($name);
    $this->assertText(t('Filter aliases'), 'English URL alias works');
  }

  /**
   * Tests that a non-default language URL alias works.
   */
  public function testNonDefaultUrl() {
    $name = $this->randomMachineName(8);
    $edit = [];
    $edit['source'] = '/admin/config/search/path';
    $edit['alias'] = '/' . $name;
    $edit['langcode'] = 'fr';
    $this->drupalPostForm('admin/config/search/path/add', $edit, t('Save'));

    $this->drupalGet('fr/' . $name);
    $this->assertText(t('Filter aliases'), 'Foreign URL alias works');
  }

}
