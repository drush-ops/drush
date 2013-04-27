<?php

/*
 * @file
 *   Tests for sitealias.inc
 *
 * @group base
 */
class saCase extends Drush_CommandTestCase {
  /*
   * Covers the following responsibilities.
   *   - Dispatching a Drush command that uses strict option handling
   *     using a site alias that contains a generic option (e.g. 'site'
   *     or 'env') that is not a recognized Drush option (maybe for
   *     use in an init hook, etc.) places the generic option BEFORE
   *     the command name.
   *   - Dispatching a Drush command that uses strict option handling
   *     using a site alias that contains a command-specific option
   *     places said option AFTER the command name.
   */
  function testDispatchStrictOptions() {
    $aliasPath = UNISH_SANDBOX . '/aliases';
    mkdir($aliasPath);
    $aliasFile = $aliasPath . '/bar.aliases.drushrc.php';
    $aliasContents = <<<EOD
  <?php
  // Writtne by Unish. This file is safe to delete.
  \$aliases['test'] = array(
    'remote-host' => 'fake.remote-host.com',
    'remote-user' => 'www-admin',
    'root' => '/fake/path/to/root',
    'uri' => 'default',
    'site' => 'stage',
    'command-specific' => array(
      'rsync' => array(
        'delete' => TRUE,
      ),
    ),
  );
EOD;
    file_put_contents($aliasFile, $aliasContents);
    $options = array(
      'alias-path' => $aliasPath,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
      'simulate' => TRUE,
    );
    $this->drush('core-rsync', array('/a', '/b'), $options, '@test');
    $output = $this->getOutput();
    $command_position = strpos($output, 'core-rsync');
    $special_option_position = strpos($output, '--site=stage');
    $command_specific_position = strpos($output, '--delete');
    $this->assertTrue($command_position !== FALSE);
    $this->assertTrue($special_option_position !== FALSE);
    $this->assertTrue($command_specific_position !== FALSE);
    $this->assertTrue($command_position > $special_option_position);
    $this->assertTrue($command_position < $command_specific_position);
  }

  /*
   * Assure that site lists work as expected.
   * @todo Use --backend for structured return data. Depends on http://drupal.org/node/1043922
   */
  public function testSAList() {
    $sites = $this->setUpDrupal(2);
    $subdirs = array_keys($sites);
    $eval = 'print "bon";';
    $options = array(
      'yes' => NULL,
      'verbose' => NULL,
      'root' => $this->webroot(),
    );
    foreach ($subdirs as $dir) {
      $dirs[] = "#$dir";
    }
    $this->drush('php-eval', array($eval), $options, implode(',', $dirs));
    $output = $this->getOutputAsList();
    $expected = "You are about to execute 'php-eval print \"bon\";' non-interactively (--yes forced) on all of the following targets:
  #dev
  #stage
Continue?  (y/n): y
#stage >> bon
#dev   >> bon";
    $this->assertEquals($expected, implode("\n", $output));
  }
}
