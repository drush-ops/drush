<?php

/*
* @file
*  Assure that context API behaves as designed. Mostly implicitly tested, but we
*  do have some edges that need explicit testing.
*
*  @see drush/includes/context.inc.
*/

class commandSpecificCase extends Drush_CommandTestCase {

  /**
   * Try to write a tiny drushrc.php to each place that drush checks. Also
   * write a sites/dev/aliases.drushrc.php file to the sandbox.
   */
  function setUp() {
    parent::setUp();

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
