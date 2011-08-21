<?php

/*
* @file
*  Assure that context API behaves as designed. Mostly implicitly tested, but we
*  do have some edges that need explicit testing.
*
*  @see drush/includes/context.inc.
*/

class contextCase extends Drush_TestCase {

  function setUpPaths() {
    $this->root = $this->sites[$this->env]['root'];
    $this->site = $this->root . '/sites/' . $this->env;
    $this->home = UNISH_SANDBOX . '/home';
    $this->paths = array(
      'custom' => UNISH_SANDBOX,
      'site' =>  $this->site,
      'drupal' => $this->root,
      'user' => $this->home,
      'home.drush' => $this->home . '/.drush',
      'system' => UNISH_SANDBOX . '/etc/drush',
      // We don't want to write a file into drush dir since it is not in the sandbox.
      // 'drush' => dirname(realpath(UNISH_DRUSH)),
    );
    // Run each path through realpath() since the paths we'll compare against
    // will have already run through drush_load_config_file().
    foreach ($this->paths as $key => $path) $this->paths[$key] = realpath($path);
  }

  /*
   * Try to write a tiny drushrc.php to each place that drush checks. Also
   * write a sites/dev/aliases.drushrc.php file to the sandbox.
   */
  function setup() {
    parent::setUp();

    $this->env = 'dev';
    $this->setUpDrupal($this->env, FALSE);
    $this->setUpPaths();

    // These files are only written to sandbox so get automatically cleaned up.
    foreach ($this->paths as $key => $path) {
      $contents = <<<EOD
<?php
// Written by Drush's contextCase::setup(). This file is safe to delete.

\$options['contextConfig'] = '$key';
\$command_specific['unit-eval']['contextConfig'] = '$key-specific';

EOD;
      $path .= $key == 'user' ? '/.drushrc.php' : '/drushrc.php';
      if (file_put_contents($path, $contents)) {
        $this->written[] = $path;
      }
    }

    // Also write a site alias so we can test its supremacy in context hierarchy.
    $path = $this->site . '/aliases.drushrc.php';
    $aliases['contextAlias'] = array(
      'contextConfig' => 'alias1',
      'command-specific' => array (
        'unit-eval' => array (
          'contextConfig' => 'alias-specific',
        ),
      ),
    );
    $contents = $this->file_aliases($aliases);
    $return = file_put_contents($path, $contents);
  }

  /*
   * These should be two different tests but I could not work out how to do that
   * without calling setup() twice. setupBeforeClass() did not work out (for MW).
   */
  function testContext() {
    $this->ConfigFile();
    $this->ContextHierarchy();
  }

  /*
   * Assure that all possible config files get loaded.
   */
  function ConfigFile() {
    $options = array(
      'pipe' => NULL,
      'config' => UNISH_SANDBOX,
      'root' => $this->root,
      'uri' => $this->env,
    );
    $this->drush('core-status', array('Drush configuration'), $options);
    $output = trim($this->getOutput());
    $loaded = explode(' ', $output);
    $this->assertSame($this->written, $loaded);
  }

  /*
   * Assure that options are loaded into right context and hierarchy is
   * respected by drush_get_option().
   *
   * Stdin context not exercised here. See backendCase::testTarget().
   */
  function ContextHierarchy() {
    // The 'custom' config file has higher priority than cli and regular config files.
    $eval =  '$contextConfig = drush_get_option("contextConfig", "n/a");';
    $eval .= '$cli1 = drush_get_option("cli1");';
    $eval .= 'print json_encode(get_defined_vars());';
    $config = UNISH_SANDBOX . '/drushrc.php';
    $options = array(
      'cli1' => NULL,
      'config' => $config,
      'root' => $this->root,
      'uri' => $this->env,
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
      'root' => $this->root,
      'uri' => $this->env,
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
      'root' => $this->root,
      'uri' => $this->env,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $this->drush('unit-eval', array($eval), $options);
    $output = $this->getOutput();
    $actuals = json_decode(trim($output));
    $this->assertEquals('site-specific', $actuals->contextConfig);
  }
}
