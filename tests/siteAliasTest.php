<?php

namespace Unish;

/**
 * Tests for sitealias.inc
 *
 * @group base
 */
class saCase extends CommandUnishTestCase {
  /**
   * Covers the following responsibilities:
   *   - Dispatching a Drush command via an alias that is defined in a
   *     site-local alias file. The target alias points to a different
   *     Drupal site at a different docroot on the same system.
   */
  function testSiteLocalAliasDispatch() {
    $sites = $this->setUpDrupal(2, TRUE);

    // Make a separate copy of the stage site so that we can test
    // to see if we can switch to a separate site via an site-local alias.
    $dev_root = $sites['dev']['root'];
    $drush_sut = dirname($dev_root);
    $other_sut = dirname($drush_sut) . '/drush-other-sut';
    $other_root = $other_sut . '/web';
    @mkdir($other_sut);
    self::recursive_copy($dev_root, $other_root);

    if (!file_exists($drush_sut . '/composer.json') || !file_exists($drush_sut . '/composer.lock')) {
      $this->markTestSkipped('This test does not run in the highest / lowest configurations.');
    }

    copy($drush_sut . '/composer.json', $other_sut . '/composer.json');
    copy($drush_sut . '/composer.lock', $other_sut . '/composer.lock');

    // Hopefully this will run quickly from the cache.
    passthru("composer --working-dir=$other_sut install");

    $aliasPath = $dev_root . '/drush';
    $aliasFile = "$aliasPath/aliases.drushrc.php";
    $aliasContents = <<<EOD
<?php
// Written by Unish. This file is safe to delete.

\$aliases["other"] = array (
  'root' => '$other_root',
  'uri' => 'stage',
);
EOD;
    if (!is_dir($aliasPath)) {
      mkdir($aliasPath);
    }
    file_put_contents($aliasFile, $aliasContents);

    // Ensure that we can access the 'other' alias from the context
    // of the 'dev' site, and that it has the right drupal root.
    $options = [
    ];
    $this->drush('sa', array('@other'), $options, '@dev');
    $output = $this->getOutput();
    $this->assertContains("root: $other_root", $output);

    // Ensure that we can get status on the 'other' alias when the
    // root of the dev site is provided (to allow Drush to find the 'other' alias)
    $options = [
      'root' => $dev_root,
      'format' => 'yaml',
    ];
    $this->drush('core-status', [], $options, '@other');
    $output = $this->getOutput();
    $this->assertContains("root: $other_root", $output);
  }

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
    $aliasPath = self::getSandbox() . '/site-alias-directory';
    file_exists($aliasPath) ?: mkdir($aliasPath);
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
    $config = self::getSandbox() . '/drushrc.php';
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

    if ($this->is_windows()) {
      $this->markTestSkipped('@todo. Needs quoting fix, and environment variables not widely used on Windows.');
    }

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
   * Ensure that a --uri on CLI overrides on provided by site alias during a backend invoke.
   */
  public function testBackendHonorsAliasOverride() {
    // Test a standard remote dispatch.
    $siteSpec = 'user@server/path/to/drupal#sitename';
    $this->drush('core-status', array(), array('uri' => 'http://example.com', 'simulate' => NULL), $siteSpec);
    $this->assertContains('--uri=http://example.com', $this->getOutput());

    // Test a local-handling command which uses drush_redispatch_get_options().
    $this->drush('browse', array(), array('uri' => 'http://example.com', 'simulate' => NULL), $siteSpec);
    $this->assertContains('--uri=http://example.com', $this->getOutput());

    // Test a command which uses drush_invoke_process('@self') internally.
//    $sites = $this->setUpDrupal(1, TRUE);
//    $name = key($sites);
//    $sites_php = "\n\$sites['example.com'] = '$name';";
//    file_put_contents($sites[$name]['root'] . '/sites/sites.php', $sites_php, FILE_APPEND);
//    $this->drush('config-pull', array(), array('uri' => 'http://example.com', 'safe' => NULL, 'verbose' => NULL), '@' . $name);
//    $this->assertContains('--uri=http://example.com', $this->getErrorOutput());

    // Test a remote alias that does not have a 'root' element
    $aliasPath = self::getSandbox() . '/site-alias-directory';
    @mkdir($aliasPath);
    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['rootlessremote'] = array(
    'uri' => 'remoteuri',
    'remote-host' => 'exampleisp.com',
    'remote-user' => 'www-admin',
  );
EOD;
    file_put_contents("$aliasPath/rootlessremote.aliases.drushrc.php", $aliasContents);
    $this->drush('core-status', array(), array('uri' => 'http://example.com', 'simulate' => NULL, 'alias-path' => $aliasPath), '@rootlessremote');
    $output = $this->getOutput();
    $this->assertContains(' ssh ', $output);
    $this->assertContains('--uri=http://example.com', $output);

    // Test a remote alias that does not have a 'root' element with cwd inside a Drupal root directory
    $root = $this->webroot();
    $this->drush('core-status', array(), array('uri' => 'http://example.com', 'simulate' => NULL, 'alias-path' => $aliasPath), '@rootlessremote', $root);
    $output = $this->getOutput();
    $this->assertContains(' ssh ', $output);
    $this->assertContains('--uri=http://example.com', $output);
  }

  /**
   * Test to see if we can access aliases defined inside of
   * a provided Drupal root in various locations where they
   * may be stored.
   */
  public function testAliasFilesInDocroot() {
    $root = $this->webroot();

    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['atroot'] = array(
    'root' => '/fake/path/to/othersite',
    'uri' => 'default',
  );
EOD;
    $this->mkdir($root . "/drush");
    $this->mkdir($root . "/drush/site-aliases");
    file_put_contents($root . "/drush/site-aliases/atroot.aliases.drushrc.php", $aliasContents);

    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['insitefolder'] = array(
    'root' => '/fake/path/to/othersite',
    'uri' => 'default',
  );
EOD;
    $this->mkdir($root . "/sites/all/drush");
    $this->mkdir($root . "/sites/all/drush/site-aliases");
    file_put_contents($root . "/sites/all/drush/site-aliases/sitefolder.aliases.drushrc.php", $aliasContents);

    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['aboveroot'] = array(
    'root' => '/fake/path/to/othersite',
    'uri' => 'default',
  );
EOD;
    $this->mkdir($root . "/../drush");
    $this->mkdir($root . "/../drush/site-aliases");
    file_put_contents($root . "/../drush/site-aliases/aboveroot.aliases.drushrc.php", $aliasContents);

    // Ensure that none of these 'sa' commands return an error
    $this->drush('sa', array('@atroot'), array('debug' => NULL, 'verbose' => NULL), '@dev');
    $this->drush('sa', array('@insitefolder'), array(), '@dev');
    $this->drush('sa', array('@aboveroot'), array(), '@dev');
  }


  /**
   * Ensure that Drush searches deep inside specified search locations
   * for alias files.
   */
  public function testDeepAliasSearching() {
    $aliasPath = self::getSandbox() . '/site-alias-directory';
    file_exists($aliasPath) ?: mkdir($aliasPath);
    $deepPath = $aliasPath . '/deep';
    file_exists($deepPath) ?: mkdir($deepPath);
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

    // Verify that the files directory is not recursed into.
    $filesPath = $aliasPath . '/files';
    file_exists($filesPath) ?: mkdir($filesPath);
    $aliasFile = $filesPath . '/biz.aliases.drushrc.php';
    $aliasContents = <<<EOD
    <?php
    // Written by unish. This file is safe to delete.
    \$aliases['nope'] = array(
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

    // This should not find the '@nope' alias.
    $this->drush('sa', array('@nope'), $options, NULL, NULL, self::EXIT_ERROR);
  }
}
