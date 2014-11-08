<?php

namespace Drush\Tests\Make\Parser;

use Drush\Make\Parser\ParserYaml;

/**
 * @coversDefaultClass \Drush\Make\Parser\ParserYaml
 */
class ParserYamlTest extends \PHPUnit_Framework_testCase {

  /**
   * @var \Drush\Make\Parser\ParserInterface
   */
  protected $parser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->parser = new ParserYaml();
  }

  /**
   * @covers ::supportedFile
   */
  public function testSupportedFile() {
    // @todo allow stdin support for Yaml files as well.
    $this->assertTrue(ParserYaml::supportedFile('-'));
    $this->assertFalse(ParserYaml::supportedFile('/tmp/foo/bar/baz.make.yml'));
    $this->assertTrue(ParserYaml::supportedFile('./baz/foo.make'));
  }

  /**
   * @dataProvider providerParse
   * @covers ::parse
   */
  public function testParse($yaml, $expected) {
    $parsed = $this->parser->parse($yaml);
    $this->assertSame($expected, $parsed);
  }

  /**
   * Provides YAML snippets to test the parser.
   */
  public function providerParse() {
    $yaml = <<<'YAML'
foo:
  bar:
    baz: one
YAML;
    $snippets[] = array($yaml, array('foo' => array('bar' => array('baz' => 'one'))));

    // @todo make more tests.
    return $snippets;
  }

}
