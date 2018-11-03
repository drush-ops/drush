<?php

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\Unicode;
use PHPUnit\Framework\TestCase;

/**
 * Test unicode handling features implemented in Unicode component.
 *
 * @group Utility
 *
 * @coversDefaultClass \Drupal\Component\Utility\Unicode
 */
class UnicodeTest extends TestCase {

  /**
   * @group legacy
   * @expectedDeprecation \Drupal\Component\Utility\Unicode::setStatus() is deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0. In Drupal 9 there will be no way to set the status and in Drupal 8 this ability has been removed because mb_*() functions are supplied using Symfony's polyfill. See https://www.drupal.org/node/2850048.
   */
  public function testSetStatus() {
    Unicode::setStatus(Unicode::STATUS_SINGLEBYTE);
  }

  /**
   * Tests multibyte encoding and decoding.
   *
   * @dataProvider providerTestMimeHeader
   * @covers ::mimeHeaderEncode
   * @covers ::mimeHeaderDecode
   */
  public function testMimeHeader($value, $encoded) {
    $this->assertEquals($encoded, Unicode::mimeHeaderEncode($value));
    $this->assertEquals($value, Unicode::mimeHeaderDecode($encoded));
  }

  /**
   * Data provider for testMimeHeader().
   *
   * @see testMimeHeader()
   *
   * @return array
   *   An array containing a string and its encoded value.
   */
  public function providerTestMimeHeader() {
    return [
      ['tést.txt', '=?UTF-8?B?dMOpc3QudHh0?='],
      // Simple ASCII characters.
      ['ASCII', 'ASCII'],
    ];
  }

  /**
   * Tests multibyte strtolower.
   *
   * @dataProvider providerStrtolower
   * @covers ::strtolower
   * @covers ::caseFlip
   * @group legacy
   * @expectedDeprecation \Drupal\Component\Utility\Unicode::strtolower() is deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0. Use mb_strtolower() instead. See https://www.drupal.org/node/2850048.
   */
  public function testStrtolower($text, $expected) {
    $this->assertEquals($expected, Unicode::strtolower($text));
  }

  /**
   * Data provider for testStrtolower().
   *
   * @see testStrtolower()
   *
   * @return array
   *   An array containing a string and its lowercase version.
   */
  public function providerStrtolower() {
    return [
      ['tHe QUIcK bRoWn', 'the quick brown'],
      ['FrançAIS is ÜBER-åwesome', 'français is über-åwesome'],
      ['ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ', 'αβγδεζηθικλμνξοσὠ'],
    ];
  }

  /**
   * Tests multibyte strtoupper.
   *
   * @dataProvider providerStrtoupper
   * @covers ::strtoupper
   * @covers ::caseFlip
   * @group legacy
   * @expectedDeprecation \Drupal\Component\Utility\Unicode::strtoupper() is deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0. Use mb_strtoupper() instead. See https://www.drupal.org/node/2850048.
   */
  public function testStrtoupper($text, $expected) {
    $this->assertEquals($expected, Unicode::strtoupper($text));
  }

  /**
   * Data provider for testStrtoupper().
   *
   * @see testStrtoupper()
   *
   * @return array
   *   An array containing a string and its uppercase version.
   */
  public function providerStrtoupper() {
    return [
      ['tHe QUIcK bRoWn', 'THE QUICK BROWN'],
      ['FrançAIS is ÜBER-åwesome', 'FRANÇAIS IS ÜBER-ÅWESOME'],
      ['αβγδεζηθικλμνξοσὠ', 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ'],
    ];
  }

  /**
   * Tests multibyte ucfirst.
   *
   * @dataProvider providerUcfirst
   * @covers ::ucfirst
   */
  public function testUcfirst($text, $expected) {
    $this->assertEquals($expected, Unicode::ucfirst($text));
  }

  /**
   * Data provider for testUcfirst().
   *
   * @see testUcfirst()
   *
   * @return array
   *   An array containing a string and its uppercase first version.
   */
  public function providerUcfirst() {
    return [
      ['tHe QUIcK bRoWn', 'THe QUIcK bRoWn'],
      ['françAIS', 'FrançAIS'],
      ['über', 'Über'],
      ['åwesome', 'Åwesome'],
      // A multibyte string.
      ['σion', 'Σion'],
    ];
  }

  /**
   * Tests multibyte lcfirst.
   *
   * @dataProvider providerLcfirst
   * @covers ::lcfirst
   */
  public function testLcfirst($text, $expected) {
    $this->assertEquals($expected, Unicode::lcfirst($text));
  }

  /**
   * Data provider for testLcfirst().
   *
   * @see testLcfirst()
   *
   * @return array
   *   An array containing a string and its lowercase version.
   */
  public function providerLcfirst() {
    return [
      ['tHe QUIcK bRoWn', 'tHe QUIcK bRoWn'],
      ['FrançAIS is ÜBER-åwesome', 'françAIS is ÜBER-åwesome'],
      ['Über', 'über'],
      ['Åwesome', 'åwesome'],
      // Add a multibyte string.
      ['ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ', 'αΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ'],
    ];
  }

  /**
   * Tests multibyte ucwords.
   *
   * @dataProvider providerUcwords
   * @covers ::ucwords
   */
  public function testUcwords($text, $expected) {
    $this->assertEquals($expected, Unicode::ucwords($text));
  }

  /**
   * Data provider for testUcwords().
   *
   * @see testUcwords()
   *
   * @return array
   *   An array containing a string and its capitalized version.
   */
  public function providerUcwords() {
    return [
      ['tHe QUIcK bRoWn', 'THe QUIcK BRoWn'],
      ['françAIS', 'FrançAIS'],
      ['über', 'Über'],
      ['åwesome', 'Åwesome'],
      // Make sure we don't mangle extra spaces.
      ['frànçAIS is  über-åwesome', 'FrànçAIS Is  Über-Åwesome'],
      // Add a multibyte string.
      ['σion', 'Σion'],
    ];
  }

  /**
   * Tests multibyte strlen.
   *
   * @dataProvider providerStrlen
   * @covers ::strlen
   * @group legacy
   * @expectedDeprecation \Drupal\Component\Utility\Unicode::strlen() is deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0. Use mb_strlen() instead. See https://www.drupal.org/node/2850048.
   */
  public function testStrlen($text, $expected) {
    $this->assertEquals($expected, Unicode::strlen($text));
  }

  /**
   * Data provider for testStrlen().
   *
   * @see testStrlen()
   *
   * @return array
   *   An array containing a string and its length.
   */
  public function providerStrlen() {
    return [
      ['tHe QUIcK bRoWn', 15],
      ['ÜBER-åwesome', 12],
      ['以呂波耳・ほへとち。リヌルヲ。', 15],
    ];
  }

  /**
   * Tests multibyte substr.
   *
   * @dataProvider providerSubstr
   * @covers ::substr
   * @group legacy
   * @expectedDeprecation \Drupal\Component\Utility\Unicode::substr() is deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0. Use mb_substr() instead. See https://www.drupal.org/node/2850048.
   */
  public function testSubstr($text, $start, $length, $expected) {
    $this->assertEquals($expected, Unicode::substr($text, $start, $length));
  }

  /**
   * Data provider for testSubstr().
   *
   * @see testSubstr()
   *
   * @return array
   *   An array containing:
   *     - The string to test.
   *     - The start number to be processed by substr.
   *     - The length number to be processed by substr.
   *     - The expected string result.
   */
  public function providerSubstr() {
    return [
      ['frànçAIS is über-åwesome', 0, NULL, 'frànçAIS is über-åwesome'],
      ['frànçAIS is über-åwesome', 0, 0, ''],
      ['frànçAIS is über-åwesome', 0, 1, 'f'],
      ['frànçAIS is über-åwesome', 0, 8, 'frànçAIS'],
      ['frànçAIS is über-åwesome', 0, 23, 'frànçAIS is über-åwesom'],
      ['frànçAIS is über-åwesome', 0, 24, 'frànçAIS is über-åwesome'],
      ['frànçAIS is über-åwesome', 0, 25, 'frànçAIS is über-åwesome'],
      ['frànçAIS is über-åwesome', 0, 100, 'frànçAIS is über-åwesome'],
      ['frànçAIS is über-åwesome', 4, 4, 'çAIS'],
      ['frànçAIS is über-åwesome', 1, 0, ''],
      ['frànçAIS is über-åwesome', 100, 0, ''],
      ['frànçAIS is über-åwesome', -4, 2, 'so'],
      ['frànçAIS is über-åwesome', -4, 3, 'som'],
      ['frànçAIS is über-åwesome', -4, 4, 'some'],
      ['frànçAIS is über-åwesome', -4, 5, 'some'],
      ['frànçAIS is über-åwesome', -7, 10, 'åwesome'],
      ['frànçAIS is über-åwesome', 5, -10, 'AIS is üb'],
      ['frànçAIS is über-åwesome', 0, -10, 'frànçAIS is üb'],
      ['frànçAIS is über-åwesome', 0, -1, 'frànçAIS is über-åwesom'],
      ['frànçAIS is über-åwesome', -7, -2, 'åweso'],
      ['frànçAIS is über-åwesome', -7, -6, 'å'],
      ['frànçAIS is über-åwesome', -7, -7, ''],
      ['frànçAIS is über-åwesome', -7, -8, ''],
      ['...', 0, 2, '..'],
      ['以呂波耳・ほへとち。リヌルヲ。', 1, 3, '呂波耳'],
    ];
  }

  /**
   * Tests multibyte truncate.
   *
   * @dataProvider providerTruncate
   * @covers ::truncate
   */
  public function testTruncate($text, $max_length, $expected, $wordsafe = FALSE, $add_ellipsis = FALSE) {
    $this->assertEquals($expected, Unicode::truncate($text, $max_length, $wordsafe, $add_ellipsis));
  }

  /**
   * Data provider for testTruncate().
   *
   * @see testTruncate()
   *
   * @return array
   *   An array containing:
   *     - The string to test.
   *     - The max length to truncate this string to.
   *     - The expected string result.
   *     - (optional) Boolean for the $wordsafe flag. Defaults to FALSE.
   *     - (optional) Boolean for the $add_ellipsis flag. Defaults to FALSE.
   */
  public function providerTruncate() {
    $tests = [
      ['frànçAIS is über-åwesome', 24, 'frànçAIS is über-åwesome'],
      ['frànçAIS is über-åwesome', 23, 'frànçAIS is über-åwesom'],
      ['frànçAIS is über-åwesome', 17, 'frànçAIS is über-'],
      ['以呂波耳・ほへとち。リヌルヲ。', 6, '以呂波耳・ほ'],
      ['frànçAIS is über-åwesome', 24, 'frànçAIS is über-åwesome', FALSE, TRUE],
      ['frànçAIS is über-åwesome', 23, 'frànçAIS is über-åweso…', FALSE, TRUE],
      ['frànçAIS is über-åwesome', 17, 'frànçAIS is über…', FALSE, TRUE],
      ['123', 1, '…', TRUE, TRUE],
      ['123', 2, '1…', TRUE, TRUE],
      ['123', 3, '123', TRUE, TRUE],
      ['1234', 3, '12…', TRUE, TRUE],
      ['1234567890', 10, '1234567890', TRUE, TRUE],
      ['12345678901', 10, '123456789…', TRUE, TRUE],
      ['12345678901', 11, '12345678901', TRUE, TRUE],
      ['123456789012', 11, '1234567890…', TRUE, TRUE],
      ['12345 7890', 10, '12345 7890', TRUE, TRUE],
      ['12345 7890', 9, '12345…', TRUE, TRUE],
      ['123 567 90', 10, '123 567 90', TRUE, TRUE],
      ['123 567 901', 10, '123 567…', TRUE, TRUE],
      ['Stop. Hammertime.', 17, 'Stop. Hammertime.', TRUE, TRUE],
      ['Stop. Hammertime.', 16, 'Stop…', TRUE, TRUE],
      ['frànçAIS is über-åwesome', 24, 'frànçAIS is über-åwesome', TRUE, TRUE],
      ['frànçAIS is über-åwesome', 23, 'frànçAIS is über…', TRUE, TRUE],
      ['frànçAIS is über-åwesome', 17, 'frànçAIS is über…', TRUE, TRUE],
      ['¿Dónde está el niño?', 20, '¿Dónde está el niño?', TRUE, TRUE],
      ['¿Dónde está el niño?', 19, '¿Dónde está el…', TRUE, TRUE],
      ['¿Dónde está el niño?', 13, '¿Dónde está…', TRUE, TRUE],
      ['¿Dónde está el niño?', 10, '¿Dónde…', TRUE, TRUE],
      ['Help! Help! Help!', 17, 'Help! Help! Help!', TRUE, TRUE],
      ['Help! Help! Help!', 16, 'Help! Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 15, 'Help! Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 14, 'Help! Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 13, 'Help! Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 12, 'Help! Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 11, 'Help! Help…', TRUE, TRUE],
      ['Help! Help! Help!', 10, 'Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 9, 'Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 8, 'Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 7, 'Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 6, 'Help!…', TRUE, TRUE],
      ['Help! Help! Help!', 5, 'Help…', TRUE, TRUE],
      ['Help! Help! Help!', 4, 'Hel…', TRUE, TRUE],
      ['Help! Help! Help!', 3, 'He…', TRUE, TRUE],
      ['Help! Help! Help!', 2, 'H…', TRUE, TRUE],
    ];

    // Test truncate on text with multiple lines.
    $multi_line = <<<EOF
This is a text that spans multiple lines.
Line 2 goes here.
EOF;
    $multi_line_wordsafe = <<<EOF
This is a text that spans multiple lines.
Line 2
EOF;
    $multi_line_non_wordsafe = <<<EOF
This is a text that spans multiple lines.
Line 2 go
EOF;
    $tests[] = [$multi_line, 51, $multi_line_wordsafe, TRUE];
    $tests[] = [$multi_line, 51, $multi_line_non_wordsafe, FALSE];

    return $tests;
  }

  /**
   * Tests multibyte truncate bytes.
   *
   * @dataProvider providerTestTruncateBytes
   * @covers ::truncateBytes
   *
   * @param string $text
   *   The string to truncate.
   * @param int $max_length
   *   The upper limit on the returned string length.
   * @param string $expected
   *   The expected return from Unicode::truncateBytes().
   */
  public function testTruncateBytes($text, $max_length, $expected) {
    $this->assertEquals($expected, Unicode::truncateBytes($text, $max_length), 'The string was not correctly truncated.');
  }

  /**
   * Provides data for self::testTruncateBytes().
   *
   * @return array
   *   An array of arrays, each containing the parameters to
   *   self::testTruncateBytes().
   */
  public function providerTestTruncateBytes() {
    return [
      // String shorter than max length.
      ['Short string', 42, 'Short string'],
      // Simple string longer than max length.
      ['Longer string than previous.', 10, 'Longer str'],
      // Unicode.
      ['以呂波耳・ほへとち。リヌルヲ。', 10, '以呂波'],
    ];
  }

  /**
   * Tests UTF-8 validation.
   *
   * @dataProvider providerTestValidateUtf8
   * @covers ::validateUtf8
   *
   * @param string $text
   *   The text to validate.
   * @param bool $expected
   *   The expected return value from Unicode::validateUtf8().
   * @param string $message
   *   The message to display on failure.
   */
  public function testValidateUtf8($text, $expected, $message) {
    $this->assertEquals($expected, Unicode::validateUtf8($text), $message);
  }

  /**
   * Provides data for self::testValidateUtf8().
   *
   * Invalid UTF-8 examples sourced from http://stackoverflow.com/a/11709412/109119.
   *
   * @return array
   *   An array of arrays, each containing the parameters for
   *   self::testValidateUtf8().
   */
  public function providerTestValidateUtf8() {
    return [
      // Empty string.
      ['', TRUE, 'An empty string did not validate.'],
      // Simple text string.
      ['Simple text.', TRUE, 'A simple ASCII text string did not validate.'],
      // Invalid UTF-8, overlong 5 byte encoding.
      [chr(0xF8) . chr(0x80) . chr(0x80) . chr(0x80) . chr(0x80), FALSE, 'Invalid UTF-8 was validated.'],
      // High code-point without trailing characters.
      [chr(0xD0) . chr(0x01), FALSE, 'Invalid UTF-8 was validated.'],
    ];
  }

  /**
   * Tests UTF-8 conversion.
   *
   * @dataProvider providerTestConvertToUtf8
   * @covers ::convertToUtf8
   *
   * @param string $data
   *   The data to be converted.
   * @param string $encoding
   *   The encoding the data is in.
   * @param string|bool $expected
   *   The expected result.
   */
  public function testConvertToUtf8($data, $encoding, $expected) {
    $this->assertEquals($expected, Unicode::convertToUtf8($data, $encoding));
  }

  /**
   * Provides data to self::testConvertToUtf8().
   *
   * @return array
   *   An array of arrays, each containing the parameters to
   *   self::testConvertUtf8().  }
   */
  public function providerTestConvertToUtf8() {
    return [
      [chr(0x97), 'Windows-1252', '—'],
      [chr(0x99), 'Windows-1252', '™'],
      [chr(0x80), 'Windows-1252', '€'],
    ];
  }

  /**
   * Tests multibyte strpos.
   *
   * @dataProvider providerStrpos
   * @covers ::strpos
   * @group legacy
   * @expectedDeprecation \Drupal\Component\Utility\Unicode::strpos() is deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0. Use mb_strpos() instead. See https://www.drupal.org/node/2850048.
   */
  public function testStrpos($haystack, $needle, $offset, $expected) {
    $this->assertEquals($expected, Unicode::strpos($haystack, $needle, $offset));
  }

  /**
   * Data provider for testStrpos().
   *
   * @see testStrpos()
   *
   * @return array
   *   An array containing:
   *     - The haystack string to be searched in.
   *     - The needle string to search for.
   *     - The offset integer to start at.
   *     - The expected integer/FALSE result.
   */
  public function providerStrpos() {
    return [
      ['frànçAIS is über-åwesome', 'frànçAIS is über-åwesome', 0, 0],
      ['frànçAIS is über-åwesome', 'rànçAIS is über-åwesome', 0, 1],
      ['frànçAIS is über-åwesome', 'not in string', 0, FALSE],
      ['frànçAIS is über-åwesome', 'r', 0, 1],
      ['frànçAIS is über-åwesome', 'nçAIS', 0, 3],
      ['frànçAIS is über-åwesome', 'nçAIS', 2, 3],
      ['frànçAIS is über-åwesome', 'nçAIS', 3, 3],
      ['以呂波耳・ほへとち。リヌルヲ。', '波耳', 0, 2],
      ['以呂波耳・ほへとち。リヌルヲ。', '波耳', 1, 2],
      ['以呂波耳・ほへとち。リヌルヲ。', '波耳', 2, 2],
    ];
  }

}
