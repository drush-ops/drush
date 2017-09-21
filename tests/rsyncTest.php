<?php

namespace Unish;

/**
 * @file
 *   Tests for rsync command
 *
 * @group commands
 */
class rsyncCase extends CommandUnishTestCase {

  /**
   * Test drush rsync --simulate.
   */
  public function testSimulated() {
    if ($this->is_windows()) {
      $this->markTestSkipped('rsync command not currently available on Windows.');
    }

    $options = [
      'simulate' => NULL,
      'alias-path' => __DIR__ . '/resources/alias-fixtures',
    ];

    // Test simulated backend invoke
    $this->drush('rsync', ['@example.dev', '@example.stage'], $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
    $expected = "Simulating backend invoke: ssh -o PasswordAuthentication=no user@server 'drush --alias-path=__DIR__/resources/alias-fixtures --root=/path/to/drupal --uri=sitename --no-ansi rsync '\''@example.dev'\'' '\''@example.stage'\'' 2>&1' 2>&1";
    $this->assertOutputEquals($expected);

    // Test simulated simple rsync with two local sites
    $this->drush('rsync', ['@example.dev', '@example.stage'], $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $expected = "Calling system(rsync -e 'ssh ' -akz /path/to/dev /path/to/stage);";
    $this->assertOutputEquals($expected);

    // Test simulated rsync with relative paths
    $this->drush('rsync', ['@example.dev:files', '@example.stage:files'], $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $expected = "Calling system(rsync -e 'ssh ' -akz /path/to/dev/files /path/to/stage/files);";
    $this->assertOutputEquals($expected);
  }

  public function testRsyncPathAliases() {
    $this->markTestSkipped('rsync command path alias replacement not working yet.');

    $sites = $this->setUpDrupal(2, TRUE);

    $options = [
      'alias-path' => __DIR__ . '/resources/alias-fixtures',
    ];

    $source = $this->webroot() . '/sites/dev/files/a';
    $target = $this->webroot() . '/sites/dev/files/b';

    @mkdir($source);
    @mkdir($target);

    $source_file = "$source/example.txt";
    $target_file = "$target/example.txt";

    // Delete target file just to be sure that we are running a clean test.
    if (file_exists($target_file)) {
      unlink($target_file);
    }

    // Create something on the dev site at $source for us to copy
    $test_data = "This is my test data";
    file_put_contents($source_file, $test_data);

    // We just deleted it -- should be missing
    $this->assertFalse(file_exists($target_file));

    // Test an actual rsync between our two fixture sites. Note that
    // these sites share the same web root.
    $this->drush('rsync', ['@unish.dev:%files/a', '@unish.stage:%files/b'], $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $expected = '';
    $this->assertOutputEquals($expected);

    // Test to see if our fixture file now exists at $target
    $this->assertTrue(file_exists($target_file));
    $actual = file_get_contents($target_file);
    $this->assertEquals($test_data, $actual);
  }

  /**
   * Test to see if the output is what we expected.
   */
  protected function assertOutputEquals($expected)
  {
    $output = $this->getOutput();
    // We do not care if Drush inserts a -t or not in the string. Depends on whether there is a tty.
    $output = preg_replace('# -t #', ' ', $output);
    // Remove double spaces from output to help protect test from false negatives if spacing changes subtlely
    $output = preg_replace('#  *#', ' ', $output);
    // Get rid of any full paths in the output
    $output = str_replace(__DIR__, '__DIR__', $output);
    $this->assertEquals($expected, $output);
  }
}
