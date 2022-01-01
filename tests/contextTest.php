<?php

/**
* @file
*  Assure that context API behaves as designed. Mostly implicitly tested, but we
*  do have some edges that need explicit testing.
*
*  @see drush/includes/context.inc.
*
*  @group base
*/

namespace Unish;

class contextCase extends CommandUnishTestCase {

  function setUpPaths() {
    $this->log("webroot: " . $this->webroot() . "\n", 'warning');
    $this->env = key($this->getSites());
    $this->site = $this->webroot() . '/sites/' . $this->env;
    $this->home = UNISH_SANDBOX . '/home';
    $this->paths = array(
      'custom' => UNISH_SANDBOX,
      'site' =>  $this->site,
      'drupal' => $this->webroot() . '/sites/all/drush',
      'drupal-parent' => dirname($this->webroot()) . '/drush',
      'user' => $this->home,
      'home.drush' => $this->home . '/.drush',
      'system' => UNISH_SANDBOX . '/etc/drush',
      // We don't want to write a file into drush dir since it is not in the sandbox.
      // 'drush' => dirname(realpath(UNISH_DRUSH)),
    );
    // Run each path through realpath() since the paths we'll compare against
    // will have already run through drush_load_config_file().
    foreach ($this->paths as $key => $path) {
      @mkdir($path);
      $this->paths[$key] = realpath($path);
    }
  }

  /**
   * Try to write a tiny drushrc.php to each place that Drush checks. Also
   * write a sites/dev/aliases.drushrc.php file to the sandbox.
   */
  function set_up() {
    parent::set_up();

    if (!$this->getSites()) {
      $this->setUpDrupal();
    }
    $this->setUpPaths();

    // These files are only written to sandbox so get automatically cleaned up.
    foreach ($this->paths as $key => $path) {
      $contents = <<<EOD
<?php
// Written by Drush's contextCase::setUp(). This file is safe to delete.
\$options['contextConfig'] = '$key';
\$command_specific['unit-eval']['contextConfig'] = '$key-specific';
EOD;
      $path .= $key == 'user' ? '/.drushrc.php' : '/drushrc.php';
      if (file_put_contents($path, $contents)) {
        $this->written[] = $this->convert_path($path);
      }
    }

    // Also write a site alias so we can test its supremacy in context hierarchy.
    $path = $this->webroot() . '/sites/' . $this->env . '/aliases.drushrc.php';
    $aliases['contextAlias'] = array(
      'contextConfig' => 'alias1',
      'command-specific' => array (
        'unit-eval' => array (
          'contextConfig' => 'alias-specific',
        ),
      ),
    );
    $contents = $this->unish_file_aliases($aliases);
    $return = file_put_contents($path, $contents);
  }

  /**
   * Assure that all possible config files get loaded.
   */
  function testConfigSearchPaths() {
    $options = array(
      'pipe' => NULL,
      'config' => UNISH_SANDBOX,
      'root' => $this->webroot(),
      'uri' => key($this->getSites())
    );
    $this->drush('core-status', array('Drush configuration'), $options);
    $loaded = $this->getOutputFromJSON('drush-conf');
    $loaded = array_map(array(&$this, 'convert_path'), $loaded);
    $this->assertSame($this->written, $loaded);
  }

  /**
   * Assure that matching version-specific config files are loaded and others are ignored.
   */
  function testConfigVersionSpecific() {
    $major = $this->drush_major_version();
    // Arbitrarily choose the system search path.
    $path = realpath(UNISH_SANDBOX . '/etc/drush');
    $contents = <<<EOD
<?php
// Written by Unish. This file is safe to delete.
\$options['unish_foo'] = 'bar';
EOD;

    // Write matched and unmatched files to the system search path.
    $files = array(
      $path .  '/drush' . $major . 'rc.php',
      $path .  '/drush999' . 'rc.php',
    );
    mkdir($path . '/drush' . $major);
    mkdir($path . '/drush999');
    foreach ($files as $file) {
      file_put_contents($file, $contents);
    }

    $this->drush('core-status', array('Drush configuration'), array('pipe' => NULL));
    $loaded = $this->getOutputFromJSON('drush-conf');
    // Next 2 lines needed for Windows compatibility.
    $loaded = array_map(array(&$this, 'convert_path'), $loaded);
    $files = array_map(array(&$this, 'convert_path'), $files);
    $this->assertTrue(in_array($files[0], $loaded), 'Loaded a version-specific config file.');
    $this->assertFalse(in_array($files[1], $loaded), 'Did not load a mismatched version-specific config file.');
  }

  /**
   * Assure that options are loaded into right context and hierarchy is
   * respected by drush_get_option().
   *
   * Stdin context not exercised here. See backendCase::testTarget().
   */
  function testContextHierarchy() {
    // The 'custom' config file has higher priority than cli and regular config files.
    $eval =  '$contextConfig = drush_get_option("contextConfig", "n/a");';
    $eval .= '$cli1 = drush_get_option("cli1");';
    $eval .= 'print json_encode(get_defined_vars());';
    $config = UNISH_SANDBOX . '/drushrc.php';
    $options = array(
      'cli1' => NULL,
      'config' => $config,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $actuals = json_decode(trim($output));
    $this->assertEquals('custom', $actuals->contextConfig);
    $this->assertTrue($actuals->cli1);

    // Site alias trumps 'custom'.
    $eval =  '$contextConfig = drush_get_option("contextConfig", "n/a");';
    $eval .= 'print json_encode(get_defined_vars());';
    $options = array(
      'config' => $config,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
    $this->drush('php-eval', array($eval), $options, '@contextAlias');
    $output = $this->getOutput();
    $actuals = json_decode(trim($output));
    $this->assertEquals('alias1', $actuals->contextConfig);

    // Command specific wins over non-specific. If it did not, $expected would
    // be 'site'. Note we call unit-eval command in order not to purturb
    // php-eval with options in config file.
    $eval =  '$contextConfig = drush_get_option("contextConfig", "n/a");';
    $eval .= 'print json_encode(get_defined_vars());';
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $this->drush('unit-eval', array($eval), $options);
    $output = $this->getOutput();
    $actuals = json_decode(trim($output));
    $this->assertEquals('site-specific', $actuals->contextConfig);
  }
}
