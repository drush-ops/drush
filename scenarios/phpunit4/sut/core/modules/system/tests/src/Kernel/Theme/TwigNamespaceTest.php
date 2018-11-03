<?php

namespace Drupal\Tests\system\Kernel\Theme;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Twig namespaces.
 *
 * @group Theme
 */
class TwigNamespaceTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['twig_theme_test', 'twig_namespace_a', 'twig_namespace_b', 'node'];

  /**
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  protected function setUp() {
    parent::setUp();
    \Drupal::service('theme_handler')->install(['test_theme', 'bartik']);
    $this->twig = \Drupal::service('twig');
  }

  /**
   * Checks to see if a value is a twig template.
   */
  public function assertTwigTemplate($value, $message = '', $group = 'Other') {
    $this->assertTrue($value instanceof \Twig_Template, $message, $group);
  }

  /**
   * Tests template discovery using namespaces.
   */
  public function testTemplateDiscovery() {
    // Tests resolving namespaced templates in modules.
    $this->assertTwigTemplate($this->twig->resolveTemplate('@node/node.html.twig'), 'Found node.html.twig in node module.');

    // Tests resolving namespaced templates in themes.
    $this->assertTwigTemplate($this->twig->resolveTemplate('@bartik/page.html.twig'), 'Found page.html.twig in Bartik theme.');
  }

  /**
   * Tests template extension and includes using namespaces.
   */
  public function testTwigNamespaces() {
    // Test twig @extends and @include in template files.
    $test = ['#theme' => 'twig_namespace_test'];
    $this->setRawContent(\Drupal::service('renderer')->renderRoot($test));

    $this->assertText('This line is from twig_namespace_a/templates/test.html.twig');
    $this->assertText('This line is from twig_namespace_b/templates/test.html.twig');
  }

}
