<?php

namespace Drupal\Tests\language\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Functional tests for the language switching feature.
 *
 * @group language
 */
class LanguageSwitchingTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['locale', 'locale_test', 'language', 'block', 'language_test', 'menu_ui'];

  protected function setUp() {
    parent::setUp();

    // Create and log in user.
    $admin_user = $this->drupalCreateUser(['administer blocks', 'administer languages', 'access administration pages']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Functional tests for the language switcher block.
   */
  public function testLanguageBlock() {
    // Add language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Set the native language name.
    $this->saveNativeLanguageName('fr', 'français');

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => '1'];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Enable the language switching block.
    $block = $this->drupalPlaceBlock('language_block:' . LanguageInterface::TYPE_INTERFACE, [
      'id' => 'test_language_block',
      // Ensure a 2-byte UTF-8 sequence is in the tested output.
      'label' => $this->randomMachineName(8) . '×',
    ]);

    $this->doTestLanguageBlockAuthenticated($block->label());
    $this->doTestLanguageBlockAnonymous($block->label());
  }

  /**
   * For authenticated users, the "active" class is set by JavaScript.
   *
   * @param string $block_label
   *   The label of the language switching block.
   *
   * @see testLanguageBlock()
   */
  protected function doTestLanguageBlockAuthenticated($block_label) {
    // Assert that the language switching block is displayed on the frontpage.
    $this->drupalGet('');
    $this->assertText($block_label, 'Language switcher block found.');

    // Assert that each list item and anchor element has the appropriate data-
    // attributes.
    $language_switchers = $this->xpath('//div[@id=:id]/ul/li', [':id' => 'block-test-language-block']);
    $list_items = [];
    $anchors = [];
    $labels = [];
    foreach ($language_switchers as $list_item) {
      $classes = explode(" ", $list_item->getAttribute('class'));
      list($langcode) = array_intersect($classes, ['en', 'fr']);
      $list_items[] = [
        'langcode_class' => $langcode,
        'data-drupal-link-system-path' => $list_item->getAttribute('data-drupal-link-system-path'),
      ];

      $link = $list_item->find('xpath', 'a');
      $anchors[] = [
         'hreflang' => $link->getAttribute('hreflang'),
         'data-drupal-link-system-path' => $link->getAttribute('data-drupal-link-system-path'),
      ];
      $labels[] = $link->getText();
    }
    $expected_list_items = [
      0 => ['langcode_class' => 'en', 'data-drupal-link-system-path' => 'user/2'],
      1 => ['langcode_class' => 'fr', 'data-drupal-link-system-path' => 'user/2'],
    ];
    $this->assertIdentical($list_items, $expected_list_items, 'The list items have the correct attributes that will allow the drupal.active-link library to mark them as active.');
    $expected_anchors = [
      0 => ['hreflang' => 'en', 'data-drupal-link-system-path' => 'user/2'],
      1 => ['hreflang' => 'fr', 'data-drupal-link-system-path' => 'user/2'],
    ];
    $this->assertIdentical($anchors, $expected_anchors, 'The anchors have the correct attributes that will allow the drupal.active-link library to mark them as active.');
    $settings = $this->getDrupalSettings();
    $this->assertIdentical($settings['path']['currentPath'], 'user/2', 'drupalSettings.path.currentPath is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($settings['path']['isFront'], FALSE, 'drupalSettings.path.isFront is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($settings['path']['currentLanguage'], 'en', 'drupalSettings.path.currentLanguage is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($labels, ['English', 'français'], 'The language links labels are in their own language on the language switcher block.');
  }

  /**
   * For anonymous users, the "active" class is set by PHP.
   *
   * @param string $block_label
   *   The label of the language switching block.
   *
   * @see testLanguageBlock()
   */
  protected function doTestLanguageBlockAnonymous($block_label) {
    $this->drupalLogout();

    // Assert that the language switching block is displayed on the frontpage
    // and ensure that the active class is added when query params are present.
    $this->drupalGet('', ['query' => ['foo' => 'bar']]);
    $this->assertText($block_label, 'Language switcher block found.');

    // Assert that only the current language is marked as active.
    $language_switchers = $this->xpath('//div[@id=:id]/ul/li', [':id' => 'block-test-language-block']);
    $links = [
      'active' => [],
      'inactive' => [],
    ];
    $anchors = [
      'active' => [],
      'inactive' => [],
    ];
    $labels = [];
    foreach ($language_switchers as $list_item) {
      $classes = explode(" ", $list_item->getAttribute('class'));
      list($langcode) = array_intersect($classes, ['en', 'fr']);
      if (in_array('is-active', $classes)) {
        $links['active'][] = $langcode;
      }
      else {
        $links['inactive'][] = $langcode;
      }

      $link = $list_item->find('xpath', 'a');
      $anchor_classes = explode(" ", $link->getAttribute('class'));
      if (in_array('is-active', $anchor_classes)) {
        $anchors['active'][] = $langcode;
      }
      else {
        $anchors['inactive'][] = $langcode;
      }
      $labels[] = $link->getText();
    }
    $this->assertIdentical($links, ['active' => ['en'], 'inactive' => ['fr']], 'Only the current language list item is marked as active on the language switcher block.');
    $this->assertIdentical($anchors, ['active' => ['en'], 'inactive' => ['fr']], 'Only the current language anchor is marked as active on the language switcher block.');
    $this->assertIdentical($labels, ['English', 'français'], 'The language links labels are in their own language on the language switcher block.');
  }

  /**
   * Test language switcher links for domain based negotiation.
   */
  public function testLanguageBlockWithDomain() {
    // Add the Italian language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Rebuild the container so that the new language is picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $languages = $this->container->get('language_manager')->getLanguages();

    // Enable browser and URL language detection.
    $edit = [
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[weight][language-url]' => -10,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Do not allow blank domain.
    $edit = [
      'language_negotiation_url_part' => LanguageNegotiationUrl::CONFIG_DOMAIN,
      'domain[en]' => '',
    ];
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));
    $this->assertText(t('The domain may not be left blank for English'), 'The form does not allow blank domains.');

    // Change the domain for the Italian language.
    $edit = [
      'language_negotiation_url_part' => LanguageNegotiationUrl::CONFIG_DOMAIN,
      'domain[en]' => \Drupal::request()->getHost(),
      'domain[it]' => 'it.example.com',
    ];
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved'), 'Domain configuration is saved.');

    // Enable the language switcher block.
    $this->drupalPlaceBlock('language_block:' . LanguageInterface::TYPE_INTERFACE, ['id' => 'test_language_block']);

    $this->drupalGet('');

    /** @var \Drupal\Core\Routing\UrlGenerator $generator */
    $generator = $this->container->get('url_generator');

    // Verify the English URL is correct
    list($english_link) = $this->xpath('//div[@id=:id]/ul/li/a[@hreflang=:hreflang]', [
      ':id' => 'block-test-language-block',
      ':hreflang' => 'en',
    ]);
    $english_url = $generator->generateFromRoute('entity.user.canonical', ['user' => 2], ['language' => $languages['en']]);
    $this->assertEqual($english_url, $english_link->getAttribute('href'));

    // Verify the Italian URL is correct
    list($italian_link) = $this->xpath('//div[@id=:id]/ul/li/a[@hreflang=:hreflang]', [
      ':id' => 'block-test-language-block',
      ':hreflang' => 'it',
    ]);
    $italian_url = $generator->generateFromRoute('entity.user.canonical', ['user' => 2], ['language' => $languages['it']]);
    $this->assertEqual($italian_url, $italian_link->getAttribute('href'));
  }

  /**
   * Test active class on links when switching languages.
   */
  public function testLanguageLinkActiveClass() {
    // Add language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => '1'];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    $this->doTestLanguageLinkActiveClassAuthenticated();
    $this->doTestLanguageLinkActiveClassAnonymous();
  }

  /**
   * Check the path-admin class, as same as on default language.
   */
  public function testLanguageBodyClass() {
    $searched_class = 'path-admin';

    // Add language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => '1'];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Check if the default (English) admin/config page has the right class.
    $this->drupalGet('admin/config');
    $class = $this->xpath('//body[contains(@class, :class)]', [':class' => $searched_class]);
    $this->assertTrue(isset($class[0]), t('The path-admin class appears on default language.'));

    // Check if the French admin/config page has the right class.
    $this->drupalGet('fr/admin/config');
    $class = $this->xpath('//body[contains(@class, :class)]', [':class' => $searched_class]);
    $this->assertTrue(isset($class[0]), t('The path-admin class same as on default language.'));

    // The testing profile sets the user/login page as the frontpage. That
    // redirects authenticated users to their profile page, so check with an
    // anonymous user instead.
    $this->drupalLogout();

    // Check if the default (English) frontpage has the right class.
    $this->drupalGet('<front>');
    $class = $this->xpath('//body[contains(@class, :class)]', [':class' => 'path-frontpage']);
    $this->assertTrue(isset($class[0]), 'path-frontpage class found on the body tag');

    // Check if the French frontpage has the right class.
    $this->drupalGet('fr');
    $class = $this->xpath('//body[contains(@class, :class)]', [':class' => 'path-frontpage']);
    $this->assertTrue(isset($class[0]), 'path-frontpage class found on the body tag with french as the active language');

  }

  /**
   * For authenticated users, the "active" class is set by JavaScript.
   *
   * @see testLanguageLinkActiveClass()
   */
  protected function doTestLanguageLinkActiveClassAuthenticated() {
    $function_name = '#type link';
    $path = 'language_test/type-link-active-class';

    // Test links generated by the link generator on an English page.
    $current_language = 'English';
    $this->drupalGet($path);

    // Language code 'none' link should be active.
    $langcode = 'none';
    $links = $this->xpath('//a[@id = :id and @data-drupal-link-system-path = :path]', [':id' => 'no_lang_link', ':path' => $path]);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode has the correct attributes that will allow the drupal.active-link library to mark it as active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'en' link should be active.
    $langcode = 'en';
    $links = $this->xpath('//a[@id = :id and @hreflang = :lang and @data-drupal-link-system-path = :path]', [':id' => 'en_link', ':lang' => 'en', ':path' => $path]);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode has the correct attributes that will allow the drupal.active-link library to mark it as active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'fr' link should not be active.
    $langcode = 'fr';
    $links = $this->xpath('//a[@id = :id and @hreflang = :lang and @data-drupal-link-system-path = :path]', [':id' => 'fr_link', ':lang' => 'fr', ':path' => $path]);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode has the correct attributes that will allow the drupal.active-link library to NOT mark it as active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Verify that drupalSettings contains the correct values.
    $settings = $this->getDrupalSettings();
    $this->assertIdentical($settings['path']['currentPath'], $path, 'drupalSettings.path.currentPath is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($settings['path']['isFront'], FALSE, 'drupalSettings.path.isFront is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($settings['path']['currentLanguage'], 'en', 'drupalSettings.path.currentLanguage is set correctly to allow drupal.active-link to mark the correct links as active.');

    // Test links generated by the link generator on a French page.
    $current_language = 'French';
    $this->drupalGet('fr/language_test/type-link-active-class');

    // Language code 'none' link should be active.
    $langcode = 'none';
    $links = $this->xpath('//a[@id = :id and @data-drupal-link-system-path = :path]', [':id' => 'no_lang_link', ':path' => $path]);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode has the correct attributes that will allow the drupal.active-link library to mark it as active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'en' link should not be active.
    $langcode = 'en';
    $links = $this->xpath('//a[@id = :id and @hreflang = :lang and @data-drupal-link-system-path = :path]', [':id' => 'en_link', ':lang' => 'en', ':path' => $path]);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode has the correct attributes that will allow the drupal.active-link library to NOT mark it as active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'fr' link should be active.
    $langcode = 'fr';
    $links = $this->xpath('//a[@id = :id and @hreflang = :lang and @data-drupal-link-system-path = :path]', [':id' => 'fr_link', ':lang' => 'fr', ':path' => $path]);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode has the correct attributes that will allow the drupal.active-link library to mark it as active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Verify that drupalSettings contains the correct values.
    $settings = $this->getDrupalSettings();
    $this->assertIdentical($settings['path']['currentPath'], $path, 'drupalSettings.path.currentPath is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($settings['path']['isFront'], FALSE, 'drupalSettings.path.isFront is set correctly to allow drupal.active-link to mark the correct links as active.');
    $this->assertIdentical($settings['path']['currentLanguage'], 'fr', 'drupalSettings.path.currentLanguage is set correctly to allow drupal.active-link to mark the correct links as active.');
  }

  /**
   * For anonymous users, the "active" class is set by PHP.
   *
   * @see testLanguageLinkActiveClass()
   */
  protected function doTestLanguageLinkActiveClassAnonymous() {
    $function_name = '#type link';

    $this->drupalLogout();

    // Test links generated by the link generator on an English page.
    $current_language = 'English';
    $this->drupalGet('language_test/type-link-active-class');

    // Language code 'none' link should be active.
    $langcode = 'none';
    $links = $this->xpath('//a[@id = :id and contains(@class, :class)]', [':id' => 'no_lang_link', ':class' => 'is-active']);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode is marked active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'en' link should be active.
    $langcode = 'en';
    $links = $this->xpath('//a[@id = :id and contains(@class, :class)]', [':id' => 'en_link', ':class' => 'is-active']);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode is marked active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'fr' link should not be active.
    $langcode = 'fr';
    $links = $this->xpath('//a[@id = :id and not(contains(@class, :class))]', [':id' => 'fr_link', ':class' => 'is-active']);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode is NOT marked active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Test links generated by the link generator on a French page.
    $current_language = 'French';
    $this->drupalGet('fr/language_test/type-link-active-class');

    // Language code 'none' link should be active.
    $langcode = 'none';
    $links = $this->xpath('//a[@id = :id and contains(@class, :class)]', [':id' => 'no_lang_link', ':class' => 'is-active']);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode is marked active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'en' link should not be active.
    $langcode = 'en';
    $links = $this->xpath('//a[@id = :id and not(contains(@class, :class))]', [':id' => 'en_link', ':class' => 'is-active']);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode is NOT marked active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));

    // Language code 'fr' link should be active.
    $langcode = 'fr';
    $links = $this->xpath('//a[@id = :id and contains(@class, :class)]', [':id' => 'fr_link', ':class' => 'is-active']);
    $this->assertTrue(isset($links[0]), t('A link generated by :function to the current :language page with langcode :langcode is marked active.', [':function' => $function_name, ':language' => $current_language, ':langcode' => $langcode]));
  }

  /**
   * Tests language switcher links for session based negotiation.
   */
  public function testLanguageSessionSwitchLinks() {
    // Add language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Enable session language detection and selection.
    $edit = [
      'language_interface[enabled][language-url]' => FALSE,
      'language_interface[enabled][language-session]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Enable the language switching block.
    $this->drupalPlaceBlock('language_block:' . LanguageInterface::TYPE_INTERFACE, [
      'id' => 'test_language_block',
    ]);

    // Enable the main menu block.
    $this->drupalPlaceBlock('system_menu_block:main', [
      'id' => 'test_menu',
    ]);

    // Add a link to the homepage.
    $link = MenuLinkContent::create([
      'title' => 'Home',
      'menu_name' => 'main',
      'bundle' => 'menu_link_content',
      'link' => [['uri' => 'entity:user/2']],
    ]);
    $link->save();

    // Go to the homepage.
    $this->drupalGet('');
    // Click on the French link.
    $this->clickLink(t('French'));
    // There should be a query parameter to set the session language.
    $this->assertUrl('user/2', ['query' => ['language' => 'fr']]);
    // Click on the 'Home' Link.
    $this->clickLink(t('Home'));
    // There should be no query parameter.
    $this->assertUrl('user/2');
    // Click on the French link.
    $this->clickLink(t('French'));
    // There should be no query parameter.
    $this->assertUrl('user/2');
  }

  /**
   * Saves the native name of a language entity in configuration as a label.
   *
   * @param string $langcode
   *   The language code of the language.
   * @param string $label
   *   The native name of the language.
   */
  protected function saveNativeLanguageName($langcode, $label) {
    \Drupal::service('language.config_factory_override')
      ->getOverride($langcode, 'language.entity.' . $langcode)->set('label', $label)->save();
  }

}
