<?php

/*
* @file
*  Assure that context API behaves as designed. Mostly implicitly tested, but we
*  do have some edges that need explicit testing.
*
*  @see drush/includes/context.inc.
*/

class contextCase extends Drush_TestCase {

  function __construct() {
    $this->env = 'dev';
    // $this->sites[$this->env]['root']
    $this->root = UNISH_SANDBOX . '/web';
    $this->site = $this->root . '/sites/' . $this->env;
    $this->home = $this->drush_server_home();
    $this->paths = array(
      'custom' => UNISH_SANDBOX,
      'site' =>  $this->site,
      'drupal' => $this->root,
      'user' => $this->home,
      'home.drush' => $this->home . '/.drush',
      'system' => '/etc/drush',
      'drush' => dirname(realpath(UNISH_DRUSH)),
    );
    // Run each path through realpath() since the paths we'll compare against 
    // will have already run through drush_load_config_file().
    foreach ($this->paths as $key => $path) $this->paths[$key] = realpath($path);
    
    $this->paths_delete_candidates = array('user', 'home.drush', 'system', 'drush');
  }

  /*
   * Try to write a tiny drushrc.php to each place that drush checks. Also
   * write a sites/dev/aliases.drushrc.php file to the sandbox.
   *
   * @todo Weight benefit of vfsStream versus adding another dependency. See
   * http://www.phpunit.de/manual/3.5/en/test-doubles.html#test-doubles.mocking-the-filesystem
   */
  function setup() {
    parent::setUp();

    $this->setUpDrupal($this->env, FALSE);
    $root = $this->sites[$this->env]['root'];
    $site = "$root/sites/$this->env";


    foreach ($this->paths as $key => $path) {
      // Only declare harmless options as these files hang around until shutdown.
      $contents = <<<EOD
<?php
// Written by Drush's contextCase::setup(). This file is safe to delete.

\$options['contextConfig'] = '$key';
\$command_specific['unit-eval']['contextConfig'] = '$key-specific';

EOD;
      $path .= $key == 'user' ? '/.drushrc.php' : '/drushrc.php';
      if (file_exists($path)) {
        $this->exists[$key] = $path;
        if ($this->should_delete($path, $contents, $key)) {
          register_shutdown_function('unlink', $path);
        }
      }
      elseif (is_writable(dirname($path))) {
        if (file_put_contents($path, $contents)) {
          $this->exists[$key] = $path;
          if ($this->should_delete($path, $contents, $key)) {
            register_shutdown_function('unlink', $path);
          }
        }
      }
      else {
        // @todo Unwritable. Warn that some locations are not getting tested.
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
    // This file is in the sandbox so gets deleted at end/start of a test run.
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
    $this->assertSame(array_values($this->exists), $loaded);
  }

  /*
   * Assure that options are loaded into right context and hierarchy is
   * respected by drush_get_option().
   *
   * Stdin context not exercised here. See targetCase::testTarget().
   */
  function ContextHierarchy() {
    // The 'custom' config file has higher priority than cli and config files.
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

    // Command specific wins over non-specific. Note we call unit-eval command
    // in order not to purturb php-eval with options in config file.
    $eval =  '$contextConfig = drush_get_option("contextConfig", "n/a");';
    $eval .= 'print json_encode(get_defined_vars());';
    $options = array(
      'root' => $this->root,
      'uri' => $this->env,
      'config' => $config,
      'include' => dirname(__FILE__),
    );
    $this->drush('unit-eval', array($eval), $options);
    $output = $this->getOutput();
    $actuals = json_decode(trim($output));
    // @todo: why is custom-specific not winning here. bug?
    $this->assertEquals('site-specific', $actuals->contextConfig);
  }

  /**
   * Return the user's home directory.
   *
   * A copy of drush's own drush_server_home().
   */
  function drush_server_home() {
    $home = NULL;
    // $_SERVER['HOME'] isn't set on windows and generates a Notice.
    if (!empty($_SERVER['HOME'])) {
      $home = $_SERVER['HOME'];
    }
    elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
      // home on windows
      $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
    }
    return $home;
  }

  function should_delete($path, $contents, $key) {
    return in_array($key, $this->paths_delete_candidates) && file_get_contents($path) == $contents;
  }
}
