<?php

namespace Drupal\Tests\Core\Template;

use Drupal\Core\GeneratedLink;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Template\TwigExtension;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the twig extension.
 *
 * @group Template
 * @group legacy
 *
 * @coversDefaultClass \Drupal\Core\Template\TwigExtension
 */
class TwigExtensionTest extends UnitTestCase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $renderer;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $themeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $dateFormatter;

  /**
   * The system under test.
   *
   * @var \Drupal\Core\Template\TwigExtension
   */
  protected $systemUnderTest;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');
    $this->urlGenerator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $this->themeManager = $this->getMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $this->dateFormatter = $this->getMock('\Drupal\Core\Datetime\DateFormatterInterface');

    $this->systemUnderTest = new TwigExtension($this->renderer, $this->urlGenerator, $this->themeManager, $this->dateFormatter);
  }

  /**
   * Tests the escaping
   *
   * @dataProvider providerTestEscaping
   *
   * @group legacy
   */
  public function testEscaping($template, $expected) {
    $twig = new \Twig_Environment(NULL, [
      'debug' => TRUE,
      'cache' => FALSE,
      'autoescape' => 'html',
      'optimizations' => 0,
    ]);
    $twig->addExtension($this->systemUnderTest);

    $nodes = $twig->parse($twig->tokenize($template));

    $this->assertSame($expected, $nodes->getNode('body')
      ->getNode(0)
      ->getNode('expr') instanceof \Twig_Node_Expression_Filter);
  }

  /**
   * Provides tests data for testEscaping
   *
   * @return array
   *   An array of test data each containing of a twig template string and
   *   a boolean expecting whether the path will be safe.
   */
  public function providerTestEscaping() {
    return [
      ['{{ path("foo") }}', FALSE],
      ['{{ path("foo", {}) }}', FALSE],
      ['{{ path("foo", { foo: "foo" }) }}', FALSE],
      ['{{ path("foo", foo) }}', TRUE],
      ['{{ path("foo", { foo: foo }) }}', TRUE],
      ['{{ path("foo", { foo: ["foo", "bar"] }) }}', TRUE],
      ['{{ path("foo", { foo: "foo", bar: "bar" }) }}', TRUE],
      ['{{ path(name = "foo", parameters = {}) }}', FALSE],
      ['{{ path(name = "foo", parameters = { foo: "foo" }) }}', FALSE],
      ['{{ path(name = "foo", parameters = foo) }}', TRUE],
      [
        '{{ path(name = "foo", parameters = { foo: ["foo", "bar"] }) }}',
        TRUE,
      ],
      ['{{ path(name = "foo", parameters = { foo: foo }) }}', TRUE],
      [
        '{{ path(name = "foo", parameters = { foo: "foo", bar: "bar" }) }}',
        TRUE,
      ],
    ];
  }

  /**
   * Tests the active_theme function.
   *
   * @group legacy
   */
  public function testActiveTheme() {
    $active_theme = $this->getMockBuilder('\Drupal\Core\Theme\ActiveTheme')
      ->disableOriginalConstructor()
      ->getMock();
    $active_theme->expects($this->once())
      ->method('getName')
      ->willReturn('test_theme');
    $this->themeManager->expects($this->once())
      ->method('getActiveTheme')
      ->willReturn($active_theme);

    $loader = new \Twig_Loader_String();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($this->systemUnderTest);
    $result = $twig->render('{{ active_theme() }}');
    $this->assertEquals('test_theme', $result);
  }

  /**
   * Tests the format_date filter.
   */
  public function testFormatDate() {
    $this->dateFormatter->expects($this->exactly(2))
      ->method('format')
      ->willReturn('1978-11-19');

    $loader = new StringLoader();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($this->systemUnderTest);
    $result = $twig->render('{{ time|format_date("html_date") }}');
    $this->assertEquals($this->dateFormatter->format('html_date'), $result);
  }

  /**
   * Tests the active_theme_path function.
   */
  public function testActiveThemePath() {
    $active_theme = $this->getMockBuilder('\Drupal\Core\Theme\ActiveTheme')
      ->disableOriginalConstructor()
      ->getMock();
    $active_theme
      ->expects($this->once())
      ->method('getPath')
      ->willReturn('foo/bar');
    $this->themeManager->expects($this->once())
      ->method('getActiveTheme')
      ->willReturn($active_theme);

    $loader = new \Twig_Loader_String();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($this->systemUnderTest);
    $result = $twig->render('{{ active_theme_path() }}');
    $this->assertEquals('foo/bar', $result);
  }

  /**
   * Tests the escaping of objects implementing MarkupInterface.
   *
   * @covers ::escapeFilter
   *
   * @group legacy
   */
  public function testSafeStringEscaping() {
    $twig = new \Twig_Environment(NULL, [
      'debug' => TRUE,
      'cache' => FALSE,
      'autoescape' => 'html',
      'optimizations' => 0,
    ]);

    // By default, TwigExtension will attempt to cast objects to strings.
    // Ensure objects that implement MarkupInterface are unchanged.
    $safe_string = $this->getMock('\Drupal\Component\Render\MarkupInterface');
    $this->assertSame($safe_string, $this->systemUnderTest->escapeFilter($twig, $safe_string, 'html', 'UTF-8', TRUE));

    // Ensure objects that do not implement MarkupInterface are escaped.
    $string_object = new TwigExtensionTestString("<script>alert('here');</script>");
    $this->assertSame('&lt;script&gt;alert(&#039;here&#039;);&lt;/script&gt;', $this->systemUnderTest->escapeFilter($twig, $string_object, 'html', 'UTF-8', TRUE));
  }

  /**
   * @covers ::safeJoin
   */
  public function testSafeJoin() {
    $this->renderer->expects($this->any())
      ->method('render')
      ->with(['#markup' => '<strong>will be rendered</strong>', '#printed' => FALSE])
      ->willReturn('<strong>will be rendered</strong>');

    $twig_environment = $this->prophesize(TwigEnvironment::class)->reveal();

    // Simulate t().
    $markup = $this->prophesize(TranslatableMarkup::class);
    $markup->__toString()->willReturn('<em>will be markup</em>');
    $markup = $markup->reveal();

    $items = [
      '<em>will be escaped</em>',
      $markup,
      ['#markup' => '<strong>will be rendered</strong>'],
    ];
    $result = $this->systemUnderTest->safeJoin($twig_environment, $items, '<br/>');
    $this->assertEquals('&lt;em&gt;will be escaped&lt;/em&gt;<br/><em>will be markup</em><br/><strong>will be rendered</strong>', $result);

    // Ensure safe_join Twig filter supports Traversable variables.
    $items = new \ArrayObject([
      '<em>will be escaped</em>',
      $markup,
      ['#markup' => '<strong>will be rendered</strong>'],
    ]);
    $result = $this->systemUnderTest->safeJoin($twig_environment, $items, ', ');
    $this->assertEquals('&lt;em&gt;will be escaped&lt;/em&gt;, <em>will be markup</em>, <strong>will be rendered</strong>', $result);

    // Ensure safe_join Twig filter supports empty variables.
    $items = NULL;
    $result = $this->systemUnderTest->safeJoin($twig_environment, $items, '<br>');
    $this->assertEmpty($result);
  }

  /**
   * @dataProvider providerTestRenderVar
   */
  public function testRenderVar($result, $input) {
    $this->renderer->expects($this->any())
      ->method('render')
      ->with($result += ['#printed' => FALSE])
      ->willReturn('Rendered output');

    $this->assertEquals('Rendered output', $this->systemUnderTest->renderVar($input));
  }

  public function providerTestRenderVar() {
    $data = [];

    $renderable = $this->prophesize(RenderableInterface::class);
    $render_array = ['#type' => 'test', '#var' => 'giraffe'];
    $renderable->toRenderable()->willReturn($render_array);
    $data['renderable'] = [$render_array, $renderable->reveal()];

    return $data;
  }

  /**
   * @covers ::escapeFilter
   * @covers ::bubbleArgMetadata
   *
   * @group legacy
   */
  public function testEscapeWithGeneratedLink() {
    $twig = new \Twig_Environment(NULL, [
        'debug' => TRUE,
        'cache' => FALSE,
        'autoescape' => 'html',
        'optimizations' => 0,
      ]
    );

    $twig->addExtension($this->systemUnderTest);
    $link = new GeneratedLink();
    $link->setGeneratedLink('<a href="http://example.com"></a>');
    $link->addCacheTags(['foo']);
    $link->addAttachments(['library' => ['system/base']]);

    $this->renderer->expects($this->atLeastOnce())
      ->method('render')
      ->with([
        "#cache" => [
          "contexts" => [],
          "tags" => ["foo"],
          "max-age" => -1,
        ],
        "#attached" => ['library' => ['system/base']],
      ]);
    $result = $this->systemUnderTest->escapeFilter($twig, $link, 'html', NULL, TRUE);
    $this->assertEquals('<a href="http://example.com"></a>', $result);
  }

  /**
   * @covers ::renderVar
   * @covers ::bubbleArgMetadata
   */
  public function testRenderVarWithGeneratedLink() {
    $link = new GeneratedLink();
    $link->setGeneratedLink('<a href="http://example.com"></a>');
    $link->addCacheTags(['foo']);
    $link->addAttachments(['library' => ['system/base']]);

    $this->renderer->expects($this->atLeastOnce())
      ->method('render')
      ->with([
        "#cache" => [
          "contexts" => [],
          "tags" => ["foo"],
          "max-age" => -1,
        ],
        "#attached" => ['library' => ['system/base']],
      ]);
    $result = $this->systemUnderTest->renderVar($link);
    $this->assertEquals('<a href="http://example.com"></a>', $result);
  }

  /**
   * Tests creating attributes within a Twig template.
   *
   * @covers ::createAttribute
   */
  public function testCreateAttribute() {
    $loader = new StringLoader();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($this->systemUnderTest);

    $iterations = [
      ['class' => ['kittens'], 'data-toggle' => 'modal', 'data-lang' => 'es'],
      ['id' => 'puppies', 'data-value' => 'foo', 'data-lang' => 'en'],
      [],
    ];
    $result = $twig->render("{% for iteration in iterations %}<div{{ create_attribute(iteration) }}></div>{% endfor %}", ['iterations' => $iterations]);
    $expected = '<div class="kittens" data-toggle="modal" data-lang="es"></div><div id="puppies" data-value="foo" data-lang="en"></div><div></div>';
    $this->assertEquals($expected, $result);

    // Test default creation of empty attribute object and using its method.
    $result = $twig->render("<div{{ create_attribute().addClass('meow') }}></div>");
    $expected = '<div class="meow"></div>';
    $this->assertEquals($expected, $result);
  }

  /**
   * @covers ::getLink
   */
  public function testLinkWithOverriddenAttributes() {
    $url = Url::fromRoute('<front>', [], ['attributes' => ['class' => ['foo']]]);

    $build = $this->systemUnderTest->getLink('test', $url, ['class' => ['bar']]);

    $this->assertEquals(['foo', 'bar'], $build['#url']->getOption('attributes')['class']);
  }

}

class TwigExtensionTestString {

  protected $string;

  public function __construct($string) {
    $this->string = $string;
  }

  public function __toString() {
    return $this->string;
  }

}
