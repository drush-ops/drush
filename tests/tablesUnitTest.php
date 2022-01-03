<?php

namespace Unish;

/**
  *   Unit tests for table formatting.
  *
  * @group base
  */
class tablesUnitTest extends UnitUnishTestCase {
  function set_up() {
    // Bootstrap to ensure the auto-loaded is running so that Console_Table is found.
    drush_preflight();
    $this->original_columns = drush_get_context('DRUSH_COLUMNS');

    // Some table data we reuse between tests.
    $this->numbers = array(
      array('1', '12', '123'),
      array('1234', '12345', '123456'),
      array('1234567', '12345678', '123456789'),
    );
    $this->words = array(
      array('Drush is a command line shell', 'scripting interface', 'for Drupal'),
      array('A veritable', 'Swiss Army knife', 'designed to make life easier for us'),
    );
  }

  function tear_down() {
    drush_set_context('DRUSH_COLUMNS', $this->original_columns);
  }

  /**
   * Tests drush_format_table() at various table widths with automatic column
   * sizing.
   *
   * @see drush_format_table().
   */
  public function testFormatAutoWidths() {
    // print "\n'" . str_replace("\n", "' . PHP_EOL . '", $output) . "'\n";
    drush_set_context('DRUSH_COLUMNS', 16);
    $output = drush_format_table($this->numbers);
    $expected = ' 1    12   123  ' . PHP_EOL . ' 123  123  1234 ' . PHP_EOL . ' 4    45   56   ' . PHP_EOL . ' 123  123  1234 ' . PHP_EOL . ' 456  456  5678 ' . PHP_EOL . ' 7    78   9    ' . PHP_EOL;
    $this->assertEquals($expected, $output);

    drush_set_context('DRUSH_COLUMNS', 22);
    $output = drush_format_table($this->numbers);
    $expected = ' 1      12     123    ' . PHP_EOL . ' 1234   12345  123456 ' . PHP_EOL . ' 12345  12345  123456 ' . PHP_EOL . ' 67     678    789    ' . PHP_EOL;
    $this->assertEquals($expected, $output);

    drush_set_context('DRUSH_COLUMNS', 24);
    $output = drush_format_table($this->numbers);
    $expected = ' 1       12      123    ' . PHP_EOL . ' 1234    12345   123456 ' . PHP_EOL . ' 123456  123456  123456 ' . PHP_EOL . ' 7       78      789    ' . PHP_EOL;
    $this->assertEquals($expected, $output);

    drush_set_context('DRUSH_COLUMNS', 80);
    $output = drush_format_table($this->numbers);
    $expected = ' 1        12        123       ' . PHP_EOL . ' 1234     12345     123456    ' . PHP_EOL . ' 1234567  12345678  123456789 ' . PHP_EOL;
    $this->assertEquals($expected, $output);
  }

  /**
   * Tests drush_format_table() at various table widths.
   *
   * @see drush_format_table().
   */
  public function testFormatWidths() {
    // print "\n'" . str_replace("\n", "' . PHP_EOL . '", $output) . "'\n";
    drush_set_context('DRUSH_COLUMNS', 22);
    $output = drush_format_table($this->numbers, FALSE, array(2));
    $expected = ' 1   12       123     ' . PHP_EOL . ' 12  12345    123456  ' . PHP_EOL . ' 34                   ' . PHP_EOL . ' 12  1234567  1234567 ' . PHP_EOL . ' 34  8        89      ' . PHP_EOL . ' 56                   ' . PHP_EOL . ' 7                    ' . PHP_EOL;
    $this->assertEquals($expected, $output);

    $output = drush_format_table($this->numbers, FALSE, array(10));
    $expected = ' 1           12   123 ' . PHP_EOL . ' 1234        123  123 ' . PHP_EOL . '             45   456 ' . PHP_EOL . ' 1234567     123  123 ' . PHP_EOL . '             456  456 ' . PHP_EOL . '             78   789 ' . PHP_EOL;
    $this->assertEquals($expected, $output);

    $output = drush_format_table($this->numbers, FALSE, array(2, 2));
    $expected = ' 1   12  123       ' . PHP_EOL . ' 12  12  123456    ' . PHP_EOL . ' 34  34            ' . PHP_EOL . '     5             ' . PHP_EOL . ' 12  12  123456789 ' . PHP_EOL . ' 34  34            ' . PHP_EOL . ' 56  56            ' . PHP_EOL . ' 7   78            ' . PHP_EOL;
    $this->assertEquals($expected, $output);

    $output = drush_format_table($this->numbers, FALSE, array(4, 4, 4));
    $expected = ' 1     12    123  ' . PHP_EOL . ' 1234  1234  1234 ' . PHP_EOL . '       5     56   ' . PHP_EOL . ' 1234  1234  1234 ' . PHP_EOL . ' 567   5678  5678 ' . PHP_EOL . '             9    ' . PHP_EOL;
    $this->assertEquals($expected, $output);
  }

  /**
   * Tests drush_format_table() with a header.
   *
   * @see drush_format_table().
   */
  public function testFormatTableHeader() {
    drush_set_context('DRUSH_COLUMNS', 16);
    $rows = $this->numbers;
    array_unshift($rows, array('A', 'B', 'C'));
    $output = drush_format_table($rows, TRUE);
    $expected = ' A    B    C    ' . PHP_EOL . ' 1    12   123  ' . PHP_EOL . ' 123  123  1234 ' . PHP_EOL . ' 4    45   56   ' . PHP_EOL . ' 123  123  1234 ' . PHP_EOL . ' 456  456  5678 ' . PHP_EOL . ' 7    78   9    ' . PHP_EOL;
    $this->assertEquals($expected, $output);
  }

  /**
   * Tests drush_format_table() with word wrapping.
   *
   * @see drush_format_table().
   */
  public function testFormatTableWordWrap() {
    drush_set_context('DRUSH_COLUMNS', 60);
    $output = drush_format_table($this->words);
    $expected = ' Drush is a command  scripting         for Drupal         ' . PHP_EOL . ' line shell          interface                            ' . PHP_EOL . ' A veritable         Swiss Army knife  designed to make   ' . PHP_EOL . '                                       life easier for us ' . PHP_EOL;
    $this->assertEquals($expected, $output);
  }
}
