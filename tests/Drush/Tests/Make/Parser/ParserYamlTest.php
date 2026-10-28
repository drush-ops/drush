<?php

namespace Drush\Tests\Make\Parser;

use Drush\Make\Parser\ParserYaml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

#[CoversClass(ParserYaml::class)]
class ParserYamlTest extends TestCase {

  public function testSupportedFile() {
    // @todo allow stdin support for Yaml files as well.
    $this->assertFalse(ParserYaml::supportedFile('-'));
    $this->assertTrue(ParserYaml::supportedFile('/tmp/foo/bar/baz.make.yml'));
    $this->assertFalse(ParserYaml::supportedFile('./baz/foo.make'));
  }

  #[DataProvider('providerParse')]
  public function testParse($yaml, $expected) {
    $parsed = ParserYaml::parse($yaml);
    $this->assertSame($expected, $parsed);
  }

  /**
   * Provides YAML snippets to test the parser.
   */
  public static function providerParse() {
    $yaml = <<<'YAML'
foo:
  bar:
    baz: one
YAML;
    $snippets[] = array($yaml, array('foo' => array('bar' => array('baz' => 'one'))));

    $yaml = <<<'YAML'
projects:
  drupal: ~
  views:
    version: '3.0'
YAML;

    $snippets[] = array($yaml, array('projects' => array('drupal' => NULL, 'views' => array('version' => '3.0'))));

    // @todo make more tests.
    return $snippets;
  }

}
