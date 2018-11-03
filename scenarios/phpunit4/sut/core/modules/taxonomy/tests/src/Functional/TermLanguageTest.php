<?php

namespace Drupal\Tests\taxonomy\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the language functionality for the taxonomy terms.
 *
 * @group taxonomy
 */
class TermLanguageTest extends TaxonomyTestBase {

  public static $modules = ['language'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  protected function setUp() {
    parent::setUp();

    // Create an administrative user.
    $this->drupalLogin($this->drupalCreateUser(['administer taxonomy']));

    // Create a vocabulary to which the terms will be assigned.
    $this->vocabulary = $this->createVocabulary();

    // Add some custom languages.
    foreach (['aa', 'bb', 'cc'] as $language_code) {
      ConfigurableLanguage::create([
        'id' => $language_code,
        'label' => $this->randomMachineName(),
      ])->save();
    }
  }

  public function testTermLanguage() {
    // Configure the vocabulary to not hide the language selector.
    $edit = [
      'default_language[language_alterable]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->id(), $edit, t('Save'));

    // Add a term.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    // Check that we have the language selector.
    $this->assertField('edit-langcode-0-value', t('The language selector field was found on the page.'));
    // Submit the term.
    $edit = [
      'name[0][value]' => $this->randomMachineName(),
      'langcode[0][value]' => 'aa',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $terms = taxonomy_term_load_multiple_by_name($edit['name[0][value]']);
    $term = reset($terms);
    $this->assertEqual($term->language()->getId(), $edit['langcode[0][value]'], 'The term contains the correct langcode.');

    // Check if on the edit page the language is correct.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertOptionSelected('edit-langcode-0-value', $edit['langcode[0][value]'], 'The term language was correctly selected.');

    // Change the language of the term.
    $edit['langcode[0][value]'] = 'bb';
    $this->drupalPostForm('taxonomy/term/' . $term->id() . '/edit', $edit, t('Save'));

    // Check again that on the edit page the language is correct.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertOptionSelected('edit-langcode-0-value', $edit['langcode[0][value]'], 'The term language was correctly selected.');
  }

  public function testDefaultTermLanguage() {
    // Configure the vocabulary to not hide the language selector, and make the
    // default language of the terms fixed.
    $edit = [
      'default_language[langcode]' => 'bb',
      'default_language[language_alterable]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->id(), $edit, t('Save'));
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode-0-value', 'bb', 'The expected langcode was selected.');

    // Make the default language of the terms to be the current interface.
    $edit = [
      'default_language[langcode]' => 'current_interface',
      'default_language[language_alterable]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->id(), $edit, t('Save'));
    $this->drupalGet('aa/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode-0-value', 'aa', "The expected langcode, 'aa', was selected.");
    $this->drupalGet('bb/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode-0-value', 'bb', "The expected langcode, 'bb', was selected.");

    // Change the default language of the site and check if the default terms
    // language is still correctly selected.
    $this->config('system.site')->set('default_langcode', 'cc')->save();
    $edit = [
      'default_language[langcode]' => LanguageInterface::LANGCODE_SITE_DEFAULT,
      'default_language[language_alterable]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->id(), $edit, t('Save'));
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode-0-value', 'cc', "The expected langcode, 'cc', was selected.");
  }

  /**
   * Tests that translated terms are displayed correctly on the term overview.
   */
  public function testTermTranslatedOnOverviewPage() {
    // Configure the vocabulary to not hide the language selector.
    $edit = [
      'default_language[language_alterable]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->id(), $edit, t('Save'));

    // Add a term.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    // Submit the term.
    $edit = [
      'name[0][value]' => $this->randomMachineName(),
      'langcode[0][value]' => 'aa',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $terms = taxonomy_term_load_multiple_by_name($edit['name[0][value]']);
    $term = reset($terms);

    // Add a translation for that term.
    $translated_title = $this->randomMachineName();
    $term->addTranslation('bb', [
      'name' => $translated_title,
    ]);
    $term->save();

    // Overview page in the other language shows the translated term
    $this->drupalGet('bb/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    $this->assertPattern('|<a[^>]*>' . $translated_title . '</a>|', 'The term language is correct');
  }

}
