<?php

namespace Drush\Tests\Make\Parser;

use Drush\Make\Parser\ParserIni;

/**
 * @coversDefaultClass \Drush\Make\Parser\ParserIni
 */
class ParserIniTest extends \PHPUnit_Framework_testCase {

  /**
   * @var \Drush\Make\Parser\ParserInterface
   */
  protected $parser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->parser = new ParserIni();
  }

  /**
   * @covers ::supportedFile
   */
  public function testSupportedFile() {
    // @todo allow stdin support for Yaml files as well.
    $this->assertTrue(ParserIni::supportedFile('-'));
    $this->assertFalse(ParserIni::supportedFile('/tmp/foo/bar/baz.make.yml'));
    $this->assertTrue(ParserIni::supportedFile('./baz/foo.make'));
  }

  /**
   * @dataProvider providerParse
   * @covers ::parse
   */
  public function testParse($ini, $expected) {
    $parsed = $this->parser->parse($ini);
    $this->assertSame($expected, $parsed);
  }

  /**
   * Provides INI snippets to test the parser.
   */
  public function providerParse() {
    $snippets[] = array('foo[bar][baz] = one', array('foo' => array('bar' => array('baz' => 'one'))));
    $snippets[] = array("; A comment should not be part of the returned array\nprojects[] = drupal", array('projects' => array('drupal')));

    // @todo make more tests.
    return $snippets;
  }

}
