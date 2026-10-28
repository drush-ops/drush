<?php

namespace Drush\Tests\Make\Parser;

use Drush\Make\Parser\ParserIni;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

#[CoversClass(ParserIni::class)]
class ParserIniTest extends TestCase {

  public function testSupportedFile() {
    $this->assertFalse(ParserIni::supportedFile('-'));
    $this->assertFalse(ParserIni::supportedFile('/tmp/foo/bar/baz.make.yml'));
    $this->assertTrue(ParserIni::supportedFile('./baz/foo.make'));
  }

  #[DataProvider('providerParse')]
  public function testParse($ini, $expected) {
    $parsed = ParserIni::parse($ini);
    $this->assertSame($expected, $parsed);
  }

  /**
   * Provides INI snippets to test the parser.
   */
  public static function providerParse() {
    $snippets[] = array('foo[bar][baz] = one', array('foo' => array('bar' => array('baz' => 'one'))));
    $snippets[] = array("; A comment should not be part of the returned array\nprojects[] = drupal", array('projects' => array('drupal')));

    // @todo make more tests.
    return $snippets;
  }

}
