<?php

namespace Drupal\Tests\config_translation\Functional;

use Drupal\Component\Utility\Html;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Translate settings and entities to various languages.
 *
 * @group config_translation
 */
class ConfigTranslationOverviewTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'block',
    'config_test',
    'config_translation',
    'config_translation_test',
    'contact',
    'contextual',
    'entity_test_operation',
    'views',
    'views_ui',
  ];

  /**
   * Languages to enable.
   *
   * @var array
   */
  protected $langcodes = ['fr', 'ta'];

  /**
   * String translation storage object.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected $localeStorage;

  protected function setUp() {
    parent::setUp();
    $permissions = [
      'translate configuration',
      'administer languages',
      'administer site configuration',
      'administer contact forms',
      'access site-wide contact form',
      'access contextual links',
      'administer views',
    ];
    // Create and log in user.
    $this->drupalLogin($this->drupalCreateUser($permissions));

    // Add languages.
    foreach ($this->langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    $this->localeStorage = $this->container->get('locale.storage');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests the config translation mapper page.
   */
  public function testMapperListPage() {
    $this->drupalGet('admin/config/regional/config-translation');
    $this->assertLinkByHref('admin/config/regional/config-translation/config_test');
    $this->assertLinkByHref('admin/config/people/accounts/translate');
    // Make sure there is only a single operation for each dropbutton, either
    // 'List' or 'Translate'.
    foreach ($this->cssSelect('ul.dropbutton') as $i => $dropbutton) {
      $this->assertIdentical(1, count($dropbutton->findAll('xpath', 'li')));
      $this->assertTrue(($dropbutton->getText() === 'Translate') || ($dropbutton->getText() === 'List'));
    }

    $labels = [
      '&$nxd~i0',
      'some "label" with quotes',
      $this->randomString(),
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('config_test');
    foreach ($labels as $label) {
      $test_entity = $storage->create([
        'id' => $this->randomMachineName(),
        'label' => $label,
      ]);
      $test_entity->save();

      $base_url = 'admin/structure/config_test/manage/' . $test_entity->id();
      $this->drupalGet('admin/config/regional/config-translation/config_test');
      $this->assertLinkByHref($base_url . '/translate');
      $this->assertEscaped($test_entity->label());

      // Make sure there is only a single 'Translate' operation for each
      // dropbutton.
      foreach ($this->cssSelect('ul.dropbutton') as $i => $dropbutton) {
        $this->assertIdentical(1, count($dropbutton->findAll('xpath', 'li')));
        $this->assertIdentical('Translate', $dropbutton->getText());
      }

      $entity_type = \Drupal::entityManager()->getDefinition($test_entity->getEntityTypeId());
      $this->drupalGet($base_url . '/translate');

      $title = $test_entity->label() . ' ' . $entity_type->getLowercaseLabel();
      $title = 'Translations for <em class="placeholder">' . Html::escape($title) . '</em>';
      $this->assertRaw($title);
      $this->assertRaw('<th>' . t('Language') . '</th>');

      $this->drupalGet($base_url);
      $this->assertLink(t('Translate @title', ['@title' => $entity_type->getLowercaseLabel()]));
    }
  }

  /**
   * Tests availability of hidden entities in the translation overview.
   */
  public function testHiddenEntities() {
    // Hidden languages are only available to translate through the
    // configuration translation listings.
    $this->drupalGet('admin/config/regional/config-translation/configurable_language');
    $this->assertText('Not applicable');
    $this->assertLinkByHref('admin/config/regional/language/edit/zxx/translate');
    $this->assertText('Not specified');
    $this->assertLinkByHref('admin/config/regional/language/edit/und/translate');

    // Hidden date formats are only available to translate through the
    // configuration translation listings. Test a couple of them.
    $this->drupalGet('admin/config/regional/config-translation/date_format');
    $this->assertText('HTML Date');
    $this->assertLinkByHref('admin/config/regional/date-time/formats/manage/html_date/translate');
    $this->assertText('HTML Year');
    $this->assertLinkByHref('admin/config/regional/date-time/formats/manage/html_year/translate');
  }

  /**
   * Tests that overrides do not affect listing screens.
   */
  public function testListingPageWithOverrides() {
    $original_label = 'Default';
    $overridden_label = 'Overridden label';

    $config_test_storage = $this->container->get('entity.manager')->getStorage('config_test');

    // Set up an override.
    $settings['config']['config_test.dynamic.dotted.default']['label'] = (object) [
      'value' => $overridden_label,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Test that the overridden label is loaded with the entity.
    $this->assertEqual($config_test_storage->load('dotted.default')->label(), $overridden_label);

    // Test that the original label on the listing page is intact.
    $this->drupalGet('admin/config/regional/config-translation/config_test');
    $this->assertText($original_label);
    $this->assertNoText($overridden_label);
  }

}
