<?php

namespace Unish;

/**
 * Tests for the 'drush' script itself
 */
class drushScriptCase extends CommandUnishTestCase {

  /**
   * Test `PHP_OPTIONS=... drush`
   */
  public function testPhpOptionsTest() {
    // @todo: could probably run this test on mingw
    if ($this->is_windows()) {
      $this->markTestSkipped('Environment variable tests not currently functional on Windows.');
    }

    $options = array();
    $env = array('PHP_OPTIONS' => '-d default_mimetype="text/drush"');
    $this->drush('ev', array('print ini_get("default_mimetype");'), $options, NULL, NULL, self::EXIT_SUCCESS, NULL, $env);
    $output = $this->getOutput();
    $this->assertEquals('text/drush', $output);
  }

  public function testDrushFinder() {
    // We don't really need a real Drupal site; we could
    // create a fake site, as long as we had the right signature
    // files to allow us to bootstrap to the DRUPAL_ROOT phase.
    $this->setUpDrupal(1, TRUE);

    // Control: test `drush --root ` ... with no site-local Drush
    $drush_location = $this->getDrushLocation();
    $this->assertEquals(UNISH_DRUSH . '.php', $drush_location);

    // We will try copying a site-local Drush to
    // all of the various locations the 'drush finder'
    // might expect to find it.
    $drush_locations = array(
      "vendor",
      "../vendor",
      "sites/all/vendor",
      "sites/all",
    );

    $drupal_root = $this->webroot();

    foreach ($drush_locations as $drush_base) {
      $drush_root = $drupal_root . '/' . $drush_base . '/drush/drush';
      $bin_dir = $drupal_root . '/' . $drush_base . '/bin';

      $this->recursive_copy(dirname(UNISH_DRUSH), $drush_root);
      @chmod($drush_root . '/drush', 0777);
      @chmod($drush_root . '/drush.launcher', 0777);
      $this->mkdir($bin_dir);
      symlink($drush_root . '/drush', $bin_dir . '/drush');

      // Test `drush --root ` ... with a site-local Drush
      $drush_location = $this->getDrushLocation(array('root' => $drupal_root));
      $this->assertEquals(realpath($drush_root . '/drush.php'), realpath($drush_location));

      // TODO:: Test `drush --root ` ... with a site-local Drush and a Drush wrapper
      // What we will do here is write a wrapper that runs the
      // same site-local Drush, but also adds an option or two.
      // If the drush location does not change, and the options
      // added by the wrapper are set, then we know that the
      // Drush finder launched the wrapper.

      // Get rid of the symlink and site-local Drush we created
      unish_file_delete_recursive($drupal_root . '/' . $drush_base);
    }
  }

  /**
   * TODO: Create a Drush wrapper script, and copy it to
   * to the root of the fake Drupal site, and point it
   * at the specified site-local Drush script.
   */

  /**
   * Get the current location of the Drush script via
   * `drush status 'Drush script' --format=yaml`.  This
   * will return results other than UNISH_DRUSH in the
   * presence of a site-local Drush.
   */
  function getDrushLocation($options = array(), $site_specification = NULL, $env = array()) {
    $options += array(
      'format' => 'yaml',
      'verbose' => NULL,
    );
    $cd = NULL;
    $expected_return = self::EXIT_SUCCESS;
    $suffix = NULL;
    $result = $this->drush('status', array('Drush script'), $options, $site_specification, $cd, $expected_return, $suffix, $env);

    $output = $this->getOutput();
    list($key, $value) = explode(": ", $output);
    return trim($value);
  }
}
