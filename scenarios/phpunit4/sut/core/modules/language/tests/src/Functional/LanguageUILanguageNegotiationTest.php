<?php

namespace Drupal\Tests\language\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationBrowser;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSelected;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSession;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUserAdmin;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\block\Entity\Block;

/**
 * Tests the language UI for language switching.
 *
 * The uses cases that get tested, are:
 * - URL (path) > default: Test that the URL prefix setting gets precedence over
 *   the default language. The browser language preference does not have any
 *   influence.
 * - URL (path) > browser > default: Test that the URL prefix setting gets
 *   precedence over the browser language preference, which in turn gets
 *   precedence over the default language.
 * - URL (domain) > default: Tests that the URL domain setting gets precedence
 *   over the default language.
 *
 * The paths that are used for each of these, are:
 * - admin/config: Tests the UI using the precedence rules.
 * - zh-hans/admin/config: Tests the UI in Chinese.
 * - blah-blah/admin/config: Tests the 404 page.
 *
 * @group language
 */
class LanguageUILanguageNegotiationTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * We marginally use interface translation functionality here, so need to use
   * the locale module instead of language only, but the 90% of the test is
   * about the negotiation process which is solely in language module.
   *
   * @var array
   */
  public static $modules = ['locale', 'language_test', 'block', 'user', 'content_translation'];

  protected function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser(['administer languages', 'translate interface', 'access administration pages', 'administer blocks']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests for language switching by URL path.
   */
  public function testUILanguageNegotiation() {
    // A few languages to switch to.
    // This one is unknown, should get the default lang version.
    $langcode_unknown = 'blah-blah';
    // For testing browser lang preference.
    $langcode_browser_fallback = 'vi';
    // For testing path prefix.
    $langcode = 'zh-hans';
    // For setting browser language preference to 'vi'.
    $http_header_browser_fallback = ["Accept-Language" => "$langcode_browser_fallback;q=1"];
    // For setting browser language preference to some unknown.
    $http_header_blah = ["Accept-Language" => "blah;q=1"];

    // Setup the site languages by installing two languages.
    // Set the default language in order for the translated string to be registered
    // into database when seen by t(). Without doing this, our target string
    // is for some reason not found when doing translate search. This might
    // be some bug.
    $default_language = \Drupal::languageManager()->getDefaultLanguage();
    ConfigurableLanguage::createFromLangcode($langcode_browser_fallback)->save();
    $this->config('system.site')->set('default_langcode', $langcode_browser_fallback)->save();
    ConfigurableLanguage::createFromLangcode($langcode)->save();

    // We will look for this string in the admin/config screen to see if the
    // corresponding translated string is shown.
    $default_string = 'Hide descriptions';

    // First visit this page to make sure our target string is searchable.
    $this->drupalGet('admin/config');

    // Now the t()'ed string is in db so switch the language back to default.
    // This will rebuild the container so we need to rebuild the container in
    // the test environment.
    $this->config('system.site')->set('default_langcode', $default_language->getId())->save();
    $this->config('language.negotiation')->set('url.prefixes.en', '')->save();
    $this->rebuildContainer();

    // Translate the string.
    $language_browser_fallback_string = "In $langcode_browser_fallback In $langcode_browser_fallback In $langcode_browser_fallback";
    $language_string = "In $langcode In $langcode In $langcode";
    // Do a translate search of our target string.
    $search = [
      'string' => $default_string,
      'langcode' => $langcode_browser_fallback,
    ];
    $this->drupalPostForm('admin/config/regional/translate', $search, t('Filter'));
    $textarea = current($this->xpath('//textarea'));
    $lid = $textarea->getAttribute('name');
    $edit = [
      $lid => $language_browser_fallback_string,
    ];
    $this->drupalPostForm('admin/config/regional/translate', $edit, t('Save translations'));

    $search = [
      'string' => $default_string,
      'langcode' => $langcode,
    ];
    $this->drupalPostForm('admin/config/regional/translate', $search, t('Filter'));
    $textarea = current($this->xpath('//textarea'));
    $lid = $textarea->getAttribute('name');
    $edit = [
      $lid => $language_string,
    ];
    $this->drupalPostForm('admin/config/regional/translate', $edit, t('Save translations'));

    // Configure selected language negotiation to use zh-hans.
    $edit = ['selected_langcode' => $langcode];
    $this->drupalPostForm('admin/config/regional/language/detection/selected', $edit, t('Save configuration'));
    $test = [
      'language_negotiation' => [LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $language_string,
      'expected_method_id' => LanguageNegotiationSelected::METHOD_ID,
      'http_header' => $http_header_browser_fallback,
      'message' => 'SELECTED: UI language is switched based on selected language.',
    ];
    $this->doRunTest($test);

    // An invalid language is selected.
    $this->config('language.negotiation')->set('selected_langcode', NULL)->save();
    $test = [
      'language_negotiation' => [LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $default_string,
      'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
      'http_header' => $http_header_browser_fallback,
      'message' => 'SELECTED > DEFAULT: UI language is switched based on selected language.',
    ];
    $this->doRunTest($test);

    // No selected language is available.
    $this->config('language.negotiation')->set('selected_langcode', $langcode_unknown)->save();
    $test = [
      'language_negotiation' => [LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $default_string,
      'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
      'http_header' => $http_header_browser_fallback,
      'message' => 'SELECTED > DEFAULT: UI language is switched based on selected language.',
    ];
    $this->doRunTest($test);

    $tests = [
      // Default, browser preference should have no influence.
      [
        'language_negotiation' => [LanguageNegotiationUrl::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
        'path' => 'admin/config',
        'expect' => $default_string,
        'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
        'http_header' => $http_header_browser_fallback,
        'message' => 'URL (PATH) > DEFAULT: no language prefix, UI language is default and the browser language preference setting is not used.',
      ],
      // Language prefix.
      [
        'language_negotiation' => [LanguageNegotiationUrl::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
        'path' => "$langcode/admin/config",
        'expect' => $language_string,
        'expected_method_id' => LanguageNegotiationUrl::METHOD_ID,
        'http_header' => $http_header_browser_fallback,
        'message' => 'URL (PATH) > DEFAULT: with language prefix, UI language is switched based on path prefix',
      ],
      // Default, go by browser preference.
      [
        'language_negotiation' => [LanguageNegotiationUrl::METHOD_ID, LanguageNegotiationBrowser::METHOD_ID],
        'path' => 'admin/config',
        'expect' => $language_browser_fallback_string,
        'expected_method_id' => LanguageNegotiationBrowser::METHOD_ID,
        'http_header' => $http_header_browser_fallback,
        'message' => 'URL (PATH) > BROWSER: no language prefix, UI language is determined by browser language preference',
      ],
      // Prefix, switch to the language.
      [
        'language_negotiation' => [LanguageNegotiationUrl::METHOD_ID, LanguageNegotiationBrowser::METHOD_ID],
        'path' => "$langcode/admin/config",
        'expect' => $language_string,
        'expected_method_id' => LanguageNegotiationUrl::METHOD_ID,
        'http_header' => $http_header_browser_fallback,
        'message' => 'URL (PATH) > BROWSER: with language prefix, UI language is based on path prefix',
      ],
      // Default, browser language preference is not one of site's lang.
      [
        'language_negotiation' => [LanguageNegotiationUrl::METHOD_ID, LanguageNegotiationBrowser::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
        'path' => 'admin/config',
        'expect' => $default_string,
        'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
        'http_header' => $http_header_blah,
        'message' => 'URL (PATH) > BROWSER > DEFAULT: no language prefix and browser language preference set to unknown language should use default language',
      ],
    ];

    foreach ($tests as $test) {
      $this->doRunTest($test);
    }

    // Unknown language prefix should return 404.
    $definitions = \Drupal::languageManager()->getNegotiator()->getNegotiationMethods();
    // Enable only methods, which are either not limited to a specific language
    // type or are supporting the interface language type.
    $language_interface_method_definitions = array_filter($definitions, function ($method_definition) {
      return !isset($method_definition['types']) || (isset($method_definition['types']) && in_array(LanguageInterface::TYPE_INTERFACE, $method_definition['types']));
    });
    $this->config('language.types')
      ->set('negotiation.' . LanguageInterface::TYPE_INTERFACE . '.enabled', array_flip(array_keys($language_interface_method_definitions)))
      ->save();
    $this->drupalGet("$langcode_unknown/admin/config", [], $http_header_browser_fallback);
    $this->assertResponse(404, "Unknown language path prefix should return 404");

    // Set preferred langcode for user to NULL.
    $account = $this->loggedInUser;
    $account->preferred_langcode = NULL;
    $account->save();

    $test = [
      'language_negotiation' => [LanguageNegotiationUser::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $default_string,
      'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
      'http_header' => [],
      'message' => 'USER > DEFAULT: no preferred user language setting, the UI language is default',
    ];
    $this->doRunTest($test);

    // Set preferred langcode for user to unknown language.
    $account = $this->loggedInUser;
    $account->preferred_langcode = $langcode_unknown;
    $account->save();

    $test = [
      'language_negotiation' => [LanguageNegotiationUser::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $default_string,
      'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
      'http_header' => [],
      'message' => 'USER > DEFAULT: invalid preferred user language setting, the UI language is default',
    ];
    $this->doRunTest($test);

    // Set preferred langcode for user to non default.
    $account->preferred_langcode = $langcode;
    $account->save();

    $test = [
      'language_negotiation' => [LanguageNegotiationUser::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $language_string,
      'expected_method_id' => LanguageNegotiationUser::METHOD_ID,
      'http_header' => [],
      'message' => 'USER > DEFAULT: defined preferred user language setting, the UI language is based on user setting',
    ];
    $this->doRunTest($test);

    // Set preferred admin langcode for user to NULL.
    $account->preferred_admin_langcode = NULL;
    $account->save();

    $test = [
      'language_negotiation' => [LanguageNegotiationUserAdmin::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $default_string,
      'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
      'http_header' => [],
      'message' => 'USER ADMIN > DEFAULT: no preferred user admin language setting, the UI language is default',
    ];
    $this->doRunTest($test);

    // Set preferred admin langcode for user to unknown language.
    $account->preferred_admin_langcode = $langcode_unknown;
    $account->save();

    $test = [
      'language_negotiation' => [LanguageNegotiationUserAdmin::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $default_string,
      'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
      'http_header' => [],
      'message' => 'USER ADMIN > DEFAULT: invalid preferred user admin language setting, the UI language is default',
    ];
    $this->doRunTest($test);

    // Set preferred admin langcode for user to non default.
    $account->preferred_admin_langcode = $langcode;
    $account->save();

    $test = [
      'language_negotiation' => [LanguageNegotiationUserAdmin::METHOD_ID, LanguageNegotiationSelected::METHOD_ID],
      'path' => 'admin/config',
      'expect' => $language_string,
      'expected_method_id' => LanguageNegotiationUserAdmin::METHOD_ID,
      'http_header' => [],
      'message' => 'USER ADMIN > DEFAULT: defined preferred user admin language setting, the UI language is based on user setting',
    ];
    $this->doRunTest($test);

    // Go by session preference.
    $language_negotiation_session_param = $this->randomMachineName();
    $edit = ['language_negotiation_session_param' => $language_negotiation_session_param];
    $this->drupalPostForm('admin/config/regional/language/detection/session', $edit, t('Save configuration'));
    $tests = [
      [
        'language_negotiation' => [LanguageNegotiationSession::METHOD_ID],
        'path' => "admin/config",
        'expect' => $default_string,
        'expected_method_id' => LanguageNegotiatorInterface::METHOD_ID,
        'http_header' => $http_header_browser_fallback,
        'message' => 'SESSION > DEFAULT: no language given, the UI language is default',
      ],
      [
        'language_negotiation' => [LanguageNegotiationSession::METHOD_ID],
        'path' => 'admin/config',
        'path_options' => ['query' => [$language_negotiation_session_param => $langcode]],
        'expect' => $language_string,
        'expected_method_id' => LanguageNegotiationSession::METHOD_ID,
        'http_header' => $http_header_browser_fallback,
        'message' => 'SESSION > DEFAULT: language given, UI language is determined by session language preference',
      ],
    ];
    foreach ($tests as $test) {
      $this->doRunTest($test);
    }
  }

  protected function doRunTest($test) {
    $test += ['path_options' => []];
    if (!empty($test['language_negotiation'])) {
      $method_weights = array_flip($test['language_negotiation']);
      $this->container->get('language_negotiator')->saveConfiguration(LanguageInterface::TYPE_INTERFACE, $method_weights);
    }
    if (!empty($test['language_negotiation_url_part'])) {
      $this->config('language.negotiation')
        ->set('url.source', $test['language_negotiation_url_part'])
        ->save();
    }
    if (!empty($test['language_test_domain'])) {
      \Drupal::state()->set('language_test.domain', $test['language_test_domain']);
    }
    $this->container->get('language_manager')->reset();
    $this->drupalGet($test['path'], $test['path_options'], $test['http_header']);
    $this->assertText($test['expect'], $test['message']);
    $this->assertText(t('Language negotiation method: @name', ['@name' => $test['expected_method_id']]));
  }

  /**
   * Test URL language detection when the requested URL has no language.
   */
  public function testUrlLanguageFallback() {
    // Add the Italian language.
    $langcode_browser_fallback = 'it';
    ConfigurableLanguage::createFromLangcode($langcode_browser_fallback)->save();
    $languages = $this->container->get('language_manager')->getLanguages();

    // Enable the path prefix for the default language: this way any unprefixed
    // URL must have a valid fallback value.
    $edit = ['prefix[en]' => 'en'];
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));

    // Enable browser and URL language detection.
    $edit = [
      'language_interface[enabled][language-browser]' => TRUE,
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[weight][language-browser]' => -8,
      'language_interface[weight][language-url]' => -10,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));
    $this->drupalGet('admin/config/regional/language/detection');

    // Enable the language switcher block.
    $this->drupalPlaceBlock('language_block:' . LanguageInterface::TYPE_INTERFACE, ['id' => 'test_language_block']);

    // Log out, because for anonymous users, the "active" class is set by PHP
    // (which means we can easily test it here), whereas for authenticated users
    // it is set by JavaScript.
    $this->drupalLogout();

    // Place a site branding block in the header region.
    $this->drupalPlaceBlock('system_branding_block', ['region' => 'header']);

    // Access the front page without specifying any valid URL language prefix
    // and having as browser language preference a non-default language.
    $http_header = ["Accept-Language" => "$langcode_browser_fallback;q=1"];
    $language = new Language(['id' => '']);
    $this->drupalGet('', ['language' => $language], $http_header);

    // Check that the language switcher active link matches the given browser
    // language.
    $args = [':id' => 'block-test-language-block', ':url' => \Drupal::url('<front>') . $langcode_browser_fallback];
    $fields = $this->xpath('//div[@id=:id]//a[@class="language-link is-active" and starts-with(@href, :url)]', $args);
    $this->assertSame($fields[0]->getText(), $languages[$langcode_browser_fallback]->getName(), 'The browser language is the URL active language');

    // Check that URLs are rewritten using the given browser language.
    $fields = $this->xpath('//div[@class="site-name"]/a[@rel="home" and @href=:url]', $args);
    $this->assertSame($fields[0]->getText(), 'Drupal', 'URLs are rewritten using the browser language.');
  }

  /**
   * Tests URL handling when separate domains are used for multiple languages.
   */
  public function testLanguageDomain() {
    global $base_url;

    // Get the current host URI we're running on.
    $base_url_host = parse_url($base_url, PHP_URL_HOST);

    // Add the Italian language.
    ConfigurableLanguage::createFromLangcode('it')->save();

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
    $this->assertText('The domain may not be left blank for English', 'The form does not allow blank domains.');
    $this->rebuildContainer();

    // Change the domain for the Italian language.
    $edit = [
      'language_negotiation_url_part' => LanguageNegotiationUrl::CONFIG_DOMAIN,
      'domain[en]' => $base_url_host,
      'domain[it]' => 'it.example.com',
    ];
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));
    $this->assertText('The configuration options have been saved', 'Domain configuration is saved.');
    $this->rebuildContainer();

    // Try to use an invalid domain.
    $edit = [
      'language_negotiation_url_part' => LanguageNegotiationUrl::CONFIG_DOMAIN,
      'domain[en]' => $base_url_host,
      'domain[it]' => 'it.example.com/',
    ];
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));
    $this->assertRaw(t('The domain for %language may only contain the domain name, not a trailing slash, protocol and/or port.', ['%language' => 'Italian']));

    // Build the link we're going to test.
    $link = 'it.example.com' . rtrim(base_path(), '/') . '/admin';

    // Test URL in another language: http://it.example.com/admin.
    // Base path gives problems on the testbot, so $correct_link is hard-coded.
    // @see UrlAlterFunctionalTest::assertUrlOutboundAlter (path.test).
    $italian_url = Url::fromRoute('system.admin', [], ['language' => $languages['it']])->toString();
    $url_scheme = \Drupal::request()->isSecure() ? 'https://' : 'http://';
    $correct_link = $url_scheme . $link;
    $this->assertEqual($italian_url, $correct_link, format_string('The right URL (@url) in accordance with the chosen language', ['@url' => $italian_url]));

    // Test HTTPS via options.
    $italian_url = Url::fromRoute('system.admin', [], ['https' => TRUE, 'language' => $languages['it']])->toString();
    $correct_link = 'https://' . $link;
    $this->assertTrue($italian_url == $correct_link, format_string('The right HTTPS URL (via options) (@url) in accordance with the chosen language', ['@url' => $italian_url]));

    // Test HTTPS via current URL scheme.
    $request = Request::create('', 'GET', [], [], [], ['HTTPS' => 'on']);
    $this->container->get('request_stack')->push($request);
    $italian_url = Url::fromRoute('system.admin', [], ['language' => $languages['it']])->toString();
    $correct_link = 'https://' . $link;
    $this->assertTrue($italian_url == $correct_link, format_string('The right URL (via current URL scheme) (@url) in accordance with the chosen language', ['@url' => $italian_url]));
  }

  /**
   * Tests persistence of negotiation settings for the content language type.
   */
  public function testContentCustomization() {
    // Customize content language settings from their defaults.
    $edit = [
      'language_content[configurable]' => TRUE,
      'language_content[enabled][language-url]' => FALSE,
      'language_content[enabled][language-session]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Check if configurability persisted.
    $config = $this->config('language.types');
    $this->assertTrue(in_array('language_interface', $config->get('configurable')), 'Interface language is configurable.');
    $this->assertTrue(in_array('language_content', $config->get('configurable')), 'Content language is configurable.');

    // Ensure configuration was saved.
    $this->assertFalse(array_key_exists('language-url', $config->get('negotiation.language_content.enabled')), 'URL negotiation is not enabled for content.');
    $this->assertTrue(array_key_exists('language-session', $config->get('negotiation.language_content.enabled')), 'Session negotiation is enabled for content.');
  }

  /**
   * Tests if the language switcher block gets deleted when a language type has been made not configurable.
   */
  public function testDisableLanguageSwitcher() {
    $block_id = 'test_language_block';

    // Enable the language switcher block.
    $this->drupalPlaceBlock('language_block:' . LanguageInterface::TYPE_CONTENT, ['id' => $block_id]);

    // Check if the language switcher block has been created.
    $block = Block::load($block_id);
    $this->assertTrue($block, 'Language switcher block was created.');

    // Make sure language_content is not configurable.
    $edit = [
      'language_content[configurable]' => FALSE,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));
    $this->assertResponse(200);

    // Check if the language switcher block has been removed.
    $block = Block::load($block_id);
    $this->assertFalse($block, 'Language switcher block was removed.');
  }

}
