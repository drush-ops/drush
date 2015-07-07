<?php

namespace Unish;

/**
 * Tests for sitealias.inc
 *
 * @group base
 */
class saCase extends CommandUnishTestCase {
  /**
   * Covers the following responsibilities.
   *   - Dispatching a Drush command that uses strict option handling
   *     using a global option (e.g. --alias-path) places said global
   *     option BEFORE the command name.
   *   - Dispatching a Drush command that uses strict option handling
   *     using a site alias that contains a command-specific option
   *     places said option AFTER the command name.
   */
  function testDispatchStrictOptions() {
    $aliasPath = UNISH_SANDBOX . '/site-alias-directory';
    mkdir($aliasPath);
    $aliasFile = $aliasPath . '/bar.aliases.drushrc.php';
    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['test'] = array(
    'remote-host' => 'fake.remote-host.com',
    'remote-user' => 'www-admin',
    'root' => '/fake/path/to/root',
    'uri' => 'default',
    'command-specific' => array(
      'rsync' => array(
        'delete' => TRUE,
      ),
    ),
  );
  \$aliases['env-test'] = array(
    'root' => '/fake/path/to/root',
    '#env-vars' => array(
      'DRUSH_ENV_TEST' => 'WORKING_CASE',
      'DRUSH_ENV_TEST2' => '{foo:[bar:{key:"val"},bar2:{key:"long val"}]}',
      'DRUSH_ENV_TEST3' => "WORKING CASE = TRUE",
    ),
    'uri' => 'default',
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
    $global_option_position = strpos($output, '--alias-path=');
    $command_specific_position = strpos($output, '--delete');
    $this->assertTrue($command_position !== FALSE);
    $this->assertTrue($global_option_position !== FALSE);
    $this->assertTrue($command_specific_position !== FALSE);
    $this->assertTrue($command_position > $global_option_position);
    $this->assertTrue($command_position < $command_specific_position);

    $eval =  '$env_test = getenv("DRUSH_ENV_TEST");';
    $eval .= '$env_test2 = getenv("DRUSH_ENV_TEST2");';
    $eval .= 'print json_encode(get_defined_vars());';
    $config = UNISH_SANDBOX . '/drushrc.php';
    $options = array(
      'alias-path' => $aliasPath,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $this->drush('unit-eval', array($eval), $options, '@env-test');
    $output = $this->getOutput();
    $actuals = json_decode(trim($output));
    $this->assertEquals('WORKING_CASE', $actuals->env_test);
    $this->assertEquals('{foo:[bar:{key:"val"},bar2:{key:"long val"}]}', $actuals->env_test2);
    $eval = 'print getenv("DRUSH_ENV_TEST3");';
    $this->drush('unit-eval', array($eval), $options, '@env-test');
    $output = $this->getOutput();
    $this->assertEquals( "WORKING CASE = TRUE", $output);
  }

  /**
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
    $expected = "#stage >> bon
#dev   >> bon";
    $actual = implode("\n", $output);
    $actual = trim(preg_replace('/^#[a-z]* *>> *$/m', '', $actual)); // ignore blank lines
    $this->assertEquals($expected, $actual);
  }

  /**
   * Ensure that requesting a non-existent alias throws an error.
   */
  public function testBadAlias() {
    $this->drush('sa', array('@badalias'), array(), NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Ensure that Drush searches deep inside specified search locations
   * for alias files.
   */
  public function testDeepAliasSearching() {
    $aliasPath = UNISH_SANDBOX . '/site-alias-directory';
    mkdir($aliasPath);
    $deepPath = $aliasPath . '/deep';
    mkdir($deepPath);
    $aliasFile = $deepPath . '/baz.aliases.drushrc.php';
    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['deep'] = array(
    'remote-host' => 'fake.remote-host.com',
    'remote-user' => 'www-admin',
    'root' => '/fake/path/to/root',
    'uri' => 'default',
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
      'simulate' => TRUE,
    );

    $this->drush('sa', array('@deep'), $options);
  }
}
