<?php

namespace Unish;

/**
 * Tests for Shell aliases.
 *
 * @group base
 */
class shellAliasesCase extends CommandUnishTestCase {

  /**
   * Write a config file that contains the shell-aliases array.
   */
  function setUp() {
    parent::setUp();
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
    file_put_contents(self::getSandbox() . '/drushrc.php', trim($contents));
    if (!file_exists(self::getSandbox() . '/b')) {
      mkdir(self::getSandbox() . '/b');
    }
    $contents = "
      <?php

      \$options['shell-aliases'] = array(
        'also' => '!echo alternate config file included too',
      );
    ";
    file_put_contents(self::getSandbox() . '/b/drushrc.php', trim($contents));
    $aliases['myalias'] = array(
      'root' => '/path/to/drupal',
      'uri' => 'mysite.org',
      '#peer' => '@live',
      'path-aliases' => array (
        '%mypath' => '/srv/data/mypath',
        '%sandbox' => self::getSandbox(),
      ),
    );
    $contents = $this->unish_file_aliases($aliases);
    file_put_contents(self::getSandbox() . '/aliases.drushrc.php', $contents);
  }

  /**
   * Test shell aliases to Drush commands.
   */
  public function testShellAliasDrushLocal() {
    $options = array(
      'config' => self::getSandbox(),
    );
    $this->drush('glopts', array(), $options);
    $output = $this->getOutput();
    $this->assertContains('--yes', $output);
    $this->assertContains('Assume \'yes\' as answer to all prompts.', $output);
  }

  /**
   * Test shell aliases to Bash commands. Assure we pass along extra arguments
   * and options.
   */
  public function testShellAliasBashLocal() {
    $options = array(
      'config' => self::getSandbox(),
      'simulate' => NULL,
    );
    $this->drush('pull', array('origin', '--', '--rebase'), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    $output = $this->getOutput();
    $this->assertContains('Calling proc_open(git pull origin --rebase);', $output);
  }

  public function testShellAliasDrushRemote() {
    $options = array(
      'config' => self::getSandbox(),
      'simulate' => NULL,
      'ssh-options' => '',
    );
    $this->drush('glopts', array(), $options, 'user@server/path/to/drupal#sitename');
    // $expected might be different on non unix platforms. We shall see.
    // n.b. --config is not included in calls to remote systems.
    $bash = $this->escapeshellarg('drush  --config=drush-sandbox --no-ansi --uri=sitename --root=/path/to/drupal  core-topic core-global-options 2>&1');
    $expected = "Simulating backend invoke: ssh -t user@server $bash 2>&1";
    $output = $this->getOutput();
    // Remove any coverage arguments. The filename changes, so it's not possible
    // to create a string for assertEquals, and the need for both shell escaping
    // and regexp escaping different parts of the expected output for
    // assertRegexp makes it easier just to remove the argument before checking
    // the output.
    $output = preg_replace('{--drush-coverage=[^ ]+ }', '', $output);
    $output = preg_replace('{--config=[^ ]+ +}', '--config=drush-sandbox ', $output);
    $this->assertEquals($expected, $output, 'Expected remote shell alias to a drush command was built');
  }

  public function testShellAliasBashRemote() {
    $options = array(
      'config' => self::getSandbox(),
      'simulate' => NULL,
      'ssh-options' => '',
    );
    $this->drush('pull', array('origin', '--', '--rebase'), $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
    // $expected might be different on non unix platforms. We shall see.
    $exec = self::escapeshellarg('cd /path/to/drupal && git pull origin --rebase');
    $expected = "Calling proc_open(ssh  user@server $exec);";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output, 'Expected remote shell alias to a bash command was built');
  }

  /**
   * Test shell aliases with simple replacements -- no alias.
   */
  public function testShellAliasSimpleReplacement() {
    $options = array(
      'config' => self::getSandbox(),
    );
    $this->drush('echosimple', array(), $options);
    // Windows command shell prints quotes (but not always?). See http://drupal.org/node/1452944.
    $expected = '@none';
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }

  /**
   * Test shell aliases with complex replacements -- no alias.
   */
  public function testShellAliasReplacementNoAlias() {
    $options = array(
      'config' => self::getSandbox(),
    );
    // echo test has replacements that are not satisfied, so this is expected to return an error.
    $this->drush('echotest', array(), $options, NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Test shell aliases with replacements -- alias.
   */
  public function testShellAliasReplacementWithAlias() {
    $options = array(
      'config' => self::getSandbox(),
      'alias-path' => self::getSandbox(),
    );
    $this->drush('echotest', array(), $options, '@myalias');
    // Windows command shell prints quotes (not always?). See http://drupal.org/node/1452944.
    $expected = '@myalias';
    $expected .= ' /path/to/drupal /srv/data/mypath';
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }

  /**
   * Test shell aliases with replacements and compound commands.
   */
  public function testShellAliasCompoundCommands() {
    $options = array(
      'config' => self::getSandbox(),
      'alias-path' => self::getSandbox(),
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
      'config' => self::getSandbox() . "/b" . PATH_SEPARATOR . self::getSandbox(),
      'alias-path' => self::getSandbox(),
    );
    $this->drush('also', array(), $options);
    $expected = "alternate config file included too";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output);
  }

}
