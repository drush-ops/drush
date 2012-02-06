<?php

/*
* @file
*  Assure that context API behaves as designed. Mostly implicitly tested, but we
*  do have some edges that need explicit testing.
*
*  @see drush/includes/context.inc.
*/

class contextCase extends Drush_CommandTestCase {

  function setUpPaths() {
    $this->log("webroot: ".$this->webroot()."\n");
    $this->env = key($this->sites);
    $this->site = $this->webroot() . '/sites/' . $this->env;
    $this->home = UNISH_SANDBOX . '/home';
    $this->paths = array(
      'custom' => UNISH_SANDBOX,
      'site' =>  $this->site,
      'drupal' => $this->webroot() . '/sites/all/drush',
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

  /**
   * Try to write a tiny drushrc.php to each place that drush checks. Also
   * write a sites/dev/aliases.drushrc.php file to the sandbox.
   */
  function setUp() {
    parent::setUp();

//    $this->setUpDrupal();
//    $this->setUpPaths();

    $path = UNISH_SANDBOX . '/aliases.drushrc.php';
    $aliases['site1'] = array(
      'root' => UNISH_SANDBOX,
      'uri' => 'site1.com',
      'source-command-specific' => array(
        'core-rsync' => array(
          'exclude-paths' => 'excluded_by_source',
        ),
      ),
      'target-command-specific' => array(
        'core-rsync' => array(
          'exclude-paths' => 'excluded_by_target',
        ),
      ),
    );
    $contents = $this->file_aliases($aliases);
    $return = file_put_contents($path, $contents);
  }
  
  function testCommandSpecific() {
    $options = array(
      'alias-path' => UNISH_SANDBOX,
      's' => NULL,
    );
    $this->drush('core-rsync', array('/tmp', '@site1'), $options);
    $output = trim($this->getOutput());
    $this->assertContains('excluded_by_target', $output);
    $this->drush('core-rsync', array('@site1', '/tmp'), $options);
    $output = trim($this->getOutput());
    $this->assertContains('excluded_by_source', $output);
    $this->drush('core-rsync', array('@site1', '@site1'), $options);
    $output = trim($this->getOutput());
    $this->assertContains('excluded_by_target', $output);
  }
}
