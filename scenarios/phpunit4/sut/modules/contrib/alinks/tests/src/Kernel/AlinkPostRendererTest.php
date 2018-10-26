<?php

namespace Drupal\Tests\alinks\Kernel;

use Drupal\alinks\AlinkPostRenderer;
use Drupal\alinks\Entity\Keyword;
use Drupal\KernelTests\KernelTestBase;

/**
 * Class AlinkPostRendererTest.
 */
class AlinkPostRendererTest extends KernelTestBase {

  /**
   * @var array
   */
  public static $modules = ['alinks', 'link', 'system', 'user'];

  /**
   * @dataProvider replaceProvider
   */
  public function testReplace($content, $expected) {
    $keywords = [
      Keyword::create([
        'name' => 'Foobar',
        'link' => [
          'uri' => 'internal:/example',
        ],
      ]),
      Keyword::create([
        'name' => 'Katze',
        'link' => [
          'uri' => 'internal:/cat',
        ],
      ]),
    ];

    $renderer = new AlinkPostRenderer($content);
    $renderer->setKeywords($keywords);
    $this->assertEquals($expected, $renderer->replace());
  }

  /**
   *
   */
  public function replaceProvider() {
    $data = [];

    $data[] = [
      '<p>Foobar</p>',
      '<p><a href="/example">Foobar</a></p>',
    ];

    $data[] = [
      '<p>Foo bar</p>',
      '<p>Foo bar</p>',
    ];

    $data[] = [
      '<p>Foobar Foobar</p>',
      '<p><a href="/example">Foobar</a> Foobar</p>',
    ];

    $data[] = [
      '<p>FoobarFoobar</p>',
      '<p>FoobarFoobar</p>',
    ];

    $data[] = [
      '<p>This is Foobar.</p>',
      '<p>This is <a href="/example">Foobar</a>.</p>',
    ];

    $data[] = [
      '<p>This is Foobar, a test with punctuation.</p>',
      '<p>This is <a href="/example">Foobar</a>, a test with punctuation.</p>',
    ];

    $data[] = [
      '<p>Foobar?</p>',
      '<p><a href="/example">Foobar</a>?</p>',
    ];

    $data[] = [
      '<p>Foobar!</p>',
      '<p><a href="/example">Foobar</a>!</p>',
    ];

    $data[] = [
      '<p><a href="/example">Foobar</a> Foobar</p>',
      '<p><a href="/example">Foobar</a> Foobar</p>',
    ];

    $data[] = [
      '<blockquote><p>Foobar</p></blockquote>',
      '<blockquote><p><a href="/example">Foobar</a></p></blockquote>',
    ];

    // Test Stemming.
    // @see http://snowball.tartarus.org/algorithms/german/stemmer.html
    $data[] = [
      '<p>Ich habe eine Katze.</p>',
      '<p>Ich habe eine <a href="/cat">Katze</a>.</p>',
    ];

    $data[] = [
      '<p>Hunde und Katzen sind beliebte Haustiere.</p>',
      '<p>Hunde und <a href="/cat">Katzen</a> sind beliebte Haustiere.</p>',
    ];

    /*
    $data[] = [
    '<p>Kätzchen die Verkleinerungsform für Katze.</p>',
    '<p><a href="/cat">Kätzchen</a> ist die Verkleinerungsform für Katze.</p>',
    ];
     */

    $data[] = [
      '<p>Weidenkätzchen</p>',
      '<p>Weidenkätzchen</p>',
    ];

    $original = '<p>ABC <script>Foobar</script></p>';
    $replacement = <<<EOT
<p>ABC <script>
<!--//--><![CDATA[// ><!--
Foobar
//--><!]]>
</script></p>
EOT;

    $data[] = [$original, $replacement];

    $data[] = [
      '<p>ABC <span data-alink-ignore>Foobar</span></p>',
      '<p>ABC <span data-alink-ignore="">Foobar</span></p>',
    ];

    $data[] = [
      '<p data-alink-ignore>ABC Foobar</p>',
      '<p data-alink-ignore="">ABC Foobar</p>',
    ];

    return $data;
  }

}
