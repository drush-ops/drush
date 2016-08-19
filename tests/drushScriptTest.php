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

    foreach ($drush_locations as $drush_base) {
      $drush_root = $this->create_site_local_drush($drush_base);

      // Test `drush --root ` ... with a site-local Drush
      $drush_location = $this->getDrushLocation(array('root' => $this->webroot()));
      $this->assertEquals(realpath($drush_root . '/drush.php'), realpath($drush_location));
      // Ensure that --local was NOT added
      $result = $this->drush('ev', array('return drush_get_option("local");'), array('root' => $this->webroot()));
      $output = $this->getOutput();
      $this->assertEquals("", $output);

      // Run the `drush --root` test again, this time with
      // a drush.wrapper script in place.
      $this->createDrushWrapper($drush_base);
      $drush_location = $this->getDrushLocation(array('root' => $this->webroot()));
      $this->assertEquals(realpath($drush_root . '/drush.php'), realpath($drush_location));
      // Test to see if --local was added
      $result = $this->drush('ev', array('return drush_get_option("local");'), array('root' => $this->webroot()));
      $output = $this->getOutput();
      $this->assertEquals("TRUE", $output);

      // Get rid of the symlink and site-local Drush we created
      $this->remove_site_local_drush($drush_base);
    }

    // Next, try again with a site-local Drush in a location
    // that Drush does not search.
    $mysterious_location = "path/drush/does/not/search";
    $drush_root = $this->create_site_local_drush($mysterious_location);
    // We should not find the site-local Drush without a Drush wrapper.
    $drush_location = $this->getDrushLocation(array('root' => $this->webroot()));
    $this->assertEquals(UNISH_DRUSH . '.php', $drush_location);
    $this->createDrushWrapper($mysterious_location);
    // Now that there is a Drush wrapper, we should be able to find the site-local Drush.
    $drush_location = $this->getDrushLocation(array('root' => $this->webroot()));
    $this->assertEquals(realpath($drush_root . '/drush.php'), $drush_location);
  }

  /**
   * Copy UNISH_DRUSH into the specified site-local location.
   */
  function create_site_local_drush($drush_base) {
    $drush_root = $this->webroot() . '/' . $drush_base . '/drush/drush';
    $bin_dir = $this->webroot() . '/' . $drush_base . '/bin';

    $this->mkdir(dirname($drush_root));
    $this->recursive_copy(dirname(UNISH_DRUSH), $drush_root);
    @chmod($drush_root . '/drush', 0777);
    @chmod($drush_root . '/drush.launcher', 0777);
    $this->mkdir($bin_dir);
    symlink($drush_root . '/drush', $bin_dir . '/drush');

    return $drush_root;
  }

  function remove_site_local_drush($drush_base) {
    // Get rid of the symlink and site-local Drush we created
    unish_file_delete_recursive($this->webroot() . '/' . $drush_base . '/drush/drush');
    unlink($this->webroot() . '/' . $drush_base . '/bin/drush');
    if (file_exists($this->webroot() . '/drush.wrapper')) {
      unlink($this->webroot() . '/drush.wrapper');
    }
  }

  /**
   * TODO: Create a Drush wrapper script, and copy it to
   * to the root of the fake Drupal site, and point it
   * at the specified site-local Drush script.
   */
  function createDrushWrapper($drush_base) {
    $drush_launcher = $drush_base . '/drush/drush/drush.launcher';

    $drush_wrapper_src = dirname(UNISH_DRUSH) . '/examples/drush.wrapper';
    $drush_wrapper_contents = file_get_contents($drush_wrapper_src);
    $drush_wrapper_contents = preg_replace('#\.\./vendor/bin/drush.launcher#', $drush_launcher, $drush_wrapper_contents);
    $drush_wrapper_target = $this->webroot() . '/drush.wrapper';

    file_put_contents($drush_wrapper_target, $drush_wrapper_contents);
    @chmod($drush_wrapper_target, 0777);
  }

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
