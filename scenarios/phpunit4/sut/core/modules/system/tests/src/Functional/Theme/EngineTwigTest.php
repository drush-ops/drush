<?php

namespace Drupal\Tests\system\Functional\Theme;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * Tests Twig-specific theme functionality.
 *
 * @group Theme
 */
class EngineTwigTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['theme_test', 'twig_theme_test'];

  protected function setUp() {
    parent::setUp();
    \Drupal::service('theme_handler')->install(['test_theme']);
  }

  /**
   * Tests that the Twig engine handles PHP data correctly.
   */
  public function testTwigVariableDataTypes() {
    $this->config('system.theme')
      ->set('default', 'test_theme')
      ->save();
    $this->drupalGet('twig-theme-test/php-variables');
    foreach (_test_theme_twig_php_values() as $type => $value) {
      $this->assertRaw('<li>' . $type . ': ' . $value['expected'] . '</li>');
    }
  }

  /**
   * Tests the url and url_generate Twig functions.
   */
  public function testTwigUrlGenerator() {
    $this->drupalGet('twig-theme-test/url-generator');
    // Find the absolute URL of the current site.
    $url_generator = $this->container->get('url_generator');
    $expected = [
      'path (as route) not absolute: ' . $url_generator->generateFromRoute('user.register'),
      'url (as route) absolute: ' . $url_generator->generateFromRoute('user.register', [], ['absolute' => TRUE]),
      'path (as route) not absolute with fragment: ' . $url_generator->generateFromRoute('user.register', [], ['fragment' => 'bottom']),
      'url (as route) absolute despite option: ' . $url_generator->generateFromRoute('user.register', [], ['absolute' => TRUE]),
      'url (as route) absolute with fragment: ' . $url_generator->generateFromRoute('user.register', [], ['absolute' => TRUE, 'fragment' => 'bottom']),
    ];

    // Verify that url() has the ability to bubble cacheability metadata:
    // absolute URLs should bubble the 'url.site' cache context. (This only
    // needs to test that cacheability metadata is bubbled *at all*; detailed
    // tests for *which* cacheability metadata is bubbled live elsewhere.)
    $this->assertCacheContext('url.site');

    // Make sure we got something.
    $content = $this->getSession()->getPage()->getContent();
    $this->assertFalse(empty($content), 'Page content is not empty');
    foreach ($expected as $string) {
      $this->assertRaw('<div>' . $string . '</div>');
    }
  }

  /**
   * Tests the link_generator Twig functions.
   */
  public function testTwigLinkGenerator() {
    $this->drupalGet('twig-theme-test/link-generator');

    /** @var \Drupal\Core\Utility\LinkGenerator $link_generator */
    $link_generator = $this->container->get('link_generator');

    $generated_url = Url::fromRoute('user.register', [], ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
    $expected = [
      'link via the linkgenerator: ' . $link_generator->generate('register', new Url('user.register', [], ['absolute' => TRUE])),
      'link via the linkgenerator: ' . $link_generator->generate('register', new Url('user.register', [], ['absolute' => TRUE, 'attributes' => ['foo' => 'bar']])),
      'link via the linkgenerator: ' . $link_generator->generate('register', new Url('user.register', [], ['attributes' => ['foo' => 'bar', 'id' => 'kitten']])),
      'link via the linkgenerator: ' . $link_generator->generate('register', new Url('user.register', [], ['attributes' => ['id' => 'kitten']])),
      'link via the linkgenerator: ' . $link_generator->generate('register', new Url('user.register', [], ['attributes' => ['class' => ['llama', 'kitten', 'panda']]])),
      'link via the linkgenerator: ' . $link_generator->generate(Markup::create('<span>register</span>'), new Url('user.register', [], ['absolute' => TRUE])),
      'link via the linkgenerator: <a href="' . $generated_url . '"><span>register</span><svg></svg></a>',
      'link via the linkgenerator: ' . $link_generator->generate('register', new Url('user.register', [], ['attributes' => ['foo' => 'bar']])) . ' ' . $link_generator->generate('register', new Url('user.register', [], ['attributes' => ['foo' => 'bar']])),
    ];

    // Verify that link() has the ability to bubble cacheability metadata:
    // absolute URLs should bubble the 'url.site' cache context. (This only
    // needs to test that cacheability metadata is bubbled *at all*; detailed
    // tests for *which* cacheability metadata is bubbled live elsewhere.)
    $this->assertCacheContext('url.site');

    $content = $this->getSession()->getPage()->getContent();
    $this->assertFalse(empty($content), 'Page content is not empty');
    foreach ($expected as $string) {
      $this->assertRaw('<div>' . $string . '</div>');
    }
  }

  /**
   * Tests the magic url to string Twig functions.
   *
   * @see \Drupal\Core\Url
   */
  public function testTwigUrlToString() {
    $this->drupalGet('twig-theme-test/url-to-string');

    $expected = [
      'rendered url: ' . Url::fromRoute('user.register')->toString(),
    ];

    $content = $this->getSession()->getPage()->getContent();
    $this->assertFalse(empty($content), 'Page content is not empty');
    foreach ($expected as $string) {
      $this->assertRaw('<div>' . $string . '</div>');
    }
  }

  /**
   * Tests the automatic/magic calling of toString() on objects, if exists.
   */
  public function testTwigFileUrls() {
    $this->drupalGet('/twig-theme-test/file-url');
    $filepath = file_url_transform_relative(file_create_url('core/modules/system/tests/modules/twig_theme_test/twig_theme_test.js'));
    $this->assertRaw('<div>file_url: ' . $filepath . '</div>');
  }

  /**
   * Tests the attach of asset libraries.
   */
  public function testTwigAttachLibrary() {
    $this->drupalGet('/twig-theme-test/attach-library');
    $this->assertRaw('ckeditor.js');
  }

  /**
   * Tests the rendering of renderables.
   */
  public function testRenderable() {
    $this->drupalGet('/twig-theme-test/renderable');
    $this->assertRaw('<div>Example markup</div>');
  }

}
