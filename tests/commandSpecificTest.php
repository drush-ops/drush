<?php

namespace Unish;

/**
*  Assure that context API behaves as designed. Mostly implicitly tested, but we
*  do have some edges that need explicit testing.
*
*  @see drush/includes/context.inc.
*
*  @group base
*/
class commandSpecificCase extends CommandUnishTestCase {

  /**
   * Try to write a tiny drushrc.php to each place that drush checks. Also
   * write a sites/dev/aliases.drushrc.php file to the sandbox.
   */
  function set_up() {
    parent::set_up();

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
      'path-aliases' => array(
        '%files' => 'sites/default/files',
      ),
    );
    $contents = $this->unish_file_aliases($aliases);
    $return = file_put_contents($path, $contents);
  }

  function testCommandSpecific() {
    $options = array(
      'alias-path' => UNISH_SANDBOX,
      'simulate' => NULL,
      'include-vcs' => NULL,
    );
    $this->drush('core-rsync', array('/tmp', '@site1'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = trim($this->getOutput());
    $this->assertStringContainsString('excluded_by_target', $output);
    $this->drush('core-rsync', array('@site1', '/tmp'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = trim($this->getOutput());
    $this->assertStringContainsString('excluded_by_source', $output);
    $this->drush('core-rsync', array('@site1', '@site1'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = trim($this->getOutput());
    $this->assertStringContainsString('excluded_by_target', $output);
    // Now do that all again with 'exclude-files'
    $options['exclude-files'] = NULL;
    $this->drush('core-rsync', array('/tmp', '@site1'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = trim($this->getOutput());
    $this->assertStringContainsString('sites/default/files', $output);
    $this->assertStringContainsString('excluded_by_target', $output);
    $this->assertStringNotContainsString('include-vcs', $output);
    $this->assertStringNotContainsString('exclude-paths', $output);
    $this->assertStringNotContainsString('exclude-files-processed', $output);
    $this->drush('core-rsync', array('@site1', '/tmp'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = trim($this->getOutput());
    $this->assertStringContainsString('sites/default/files', $output);
// This one does not work. @see drush_sitealias_evaluate_path
//    $this->assertStringContainsString('excluded_by_source', $output);
    $this->assertStringNotContainsString('include-vcs', $output);
    $this->assertStringNotContainsString('exclude-paths', $output);
    $this->assertStringNotContainsString('exclude-files-processed', $output);
    $this->drush('core-rsync', array('@site1', '@site1'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = trim($this->getOutput());
    $this->assertStringContainsString('sites/default/files', $output);
    $this->assertStringContainsString('excluded_by_target', $output);
    $this->assertStringNotContainsString('include-vcs', $output);
    $this->assertStringNotContainsString('exclude-paths', $output);
    $this->assertStringNotContainsString('exclude-files-processed', $output);
  }
}
