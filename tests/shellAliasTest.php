<?php

/**
 * @file
 *   Tests for Shell aliases.
 *
 * @group base
 */
class shellAliasesCase extends Drush_CommandTestCase {

  /**
   * Write a config file that contains the shell-aliases array.
   */
  static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    $contents = "
      <?php

      \$options['shell-aliases'] = array(
        'glopts' => 'topic core-global-options',
        'pull' => '!git pull',
        'echosimple' => '!echo {{@target}}',
        'echotest' => '!echo {{@target}} {{%root}} {{%mypath}}',
        'compound-command' => '!cd {{%sandbox}} && echo second',
      );
    ";
    file_put_contents(UNISH_SANDBOX . '/drushrc.php', trim($contents));
    mkdir(UNISH_SANDBOX . '/b');
    $contents = "
      <?php

      \$options['shell-aliases'] = array(
        'also' => '!echo alternate config file included too',
      );
    ";
    file_put_contents(UNISH_SANDBOX . '/b/drushrc.php', trim($contents));
    $aliases['myalias'] = array(
      'root' => '/path/to/drupal',
      'uri' => 'mysite.org',
      '#peer' => '@live',
      'path-aliases' => array (
        '%mypath' => '/srv/data/mypath',
        '%sandbox' => UNISH_SANDBOX,
      ),
    );
    $contents = unish_file_aliases($aliases);
    file_put_contents(UNISH_SANDBOX . '/aliases.drushrc.php', $contents);
  }

  /**
   * Test shell aliases to Drush commands.
   */
  public function testShellAliasDrushLocal() {
    $options = array(
      'config' => UNISH_SANDBOX,
    );
    $this->drush('glopts', array(), $options);
    $output = $this->getOutput();
    $this->assertContains('These options are applicable to most drush commands.', $output, 'Successfully executed local shell alias to drush command');
  }

  /**
   * Test shell aliases to Bash commands. Assure we pass along extra arguments
   * and options.
   */
  public function testShellAliasBashLocal() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'simulate' => NULL,
      'rebase' => NULL,
    );
    $this->drush('pull', array('origin'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = $this->getOutput();
    $this->assertContains('Calling proc_open(git pull origin --rebase);', $output);
  }

  public function testShellAliasDrushRemote() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'simulate' => NULL,
      'ssh-options' => '',
    );
    $this->drush('glopts', array(), $options, 'user@server/path/to/drupal#sitename');
    // $expected might be different on non unix platforms. We shall see.
    // n.b. --config is not included in calls to remote systems.
    $bash = $this->escapeshellarg('drush  --nocolor --uri=sitename --root=/path/to/drupal  core-topic core-global-options 2>&1');
    $expected = "Simulating backend invoke: ssh user@server $bash 2>&1";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output, 'Expected remote shell alias to a drush command was built');
  }

  public function testShellAliasBashRemote() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'simulate' => NULL,
      'ssh-options' => '',
      'rebase' => NULL,
    );
    $this->drush('pull', array('origin'), $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
    // $expected might be different on non unix platforms. We shall see.
    $expected = "Calling proc_open(ssh  user@server 'cd /path/to/drupal && git pull origin --rebase');";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output, 'Expected remote shell alias to a bash command was built');
  }

  /**
   * Test shell aliases with simple replacements -- no alias.
   */
  public function testShellAliasSimpleReplacement() {
    $options = array(
      'config' => UNISH_SANDBOX,
    );
    $this->drush('echosimple', array(), $options);
    // Windows command shell actually prints quotes. See http://drupal.org/node/1452944.
    $expected = $this->is_windows() ? '"@none"' : '@none';
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }

  /**
   * Test shell aliases with complex replacements -- no alias.
   */
  public function testShellAliasReplacementNoAlias() {
    $options = array(
      'config' => UNISH_SANDBOX,
    );
    // echo test has replacements that are not satisfied, so this is expected to return an error.
    $this->drush('echotest', array(), $options, NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Test shell aliases with replacements -- alias.
   */
  public function testShellAliasReplacementWithAlias() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'alias-path' => UNISH_SANDBOX,
    );
    $this->drush('echotest', array(), $options, '@myalias');
    // Windows command shell actually prints quotes. See http://drupal.org/node/1452944.
    $expected = $this->is_windows() ? '"@myalias"' : '@myalias';
    $expected .= ' /path/to/drupal /srv/data/mypath';
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }

  /**
   * Test shell aliases with replacements and compound commands.
   */
  public function testShellAliasCompoundCommands() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'alias-path' => UNISH_SANDBOX,
    );
    $this->drush('compound-command', array(), $options, '@myalias');
    $expected = 'second';
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }


  /**
   * Test shell aliases with multiple config files.
   */
  public function testShellAliasMultipleConfigFiles() {
    $options = array(
      'config' => UNISH_SANDBOX . "/b" . PATH_SEPARATOR . UNISH_SANDBOX,
      'alias-path' => UNISH_SANDBOX,
    );
    $this->drush('also', array(), $options);
    $expected = "alternate config file included too";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }

}
