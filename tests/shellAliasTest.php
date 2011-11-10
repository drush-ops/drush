<?php

/*
 * @file
 *   Tests for Shell aliases.
 */
class shellAliasesCase extends Drush_CommandTestCase {

  /**
   * Write a config file that contains the shell-aliases array.
   */
  static function setupBeforeClass() {
    parent::setUpBeforeClass();
    $contents = "
      <?php

      \$options['shell-aliases'] = array(
        'glopts' => 'topic core-global-options',
        'pull' => '!git pull',
      );
    ";
    file_put_contents(UNISH_SANDBOX . '/drushrc.php', trim($contents));
  }

  /*
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

  /*
   * Test shell aliases to Bash commands. Assure we pass along extra arguments
   * and options.
   */
  public function testShellAliasBashLocal() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'simulate' => NULL,
      'rebase' => NULL,
    );
    $this->drush('pull', array('origin'), $options);
    $output = $this->getOutput();
    $this->assertContains('Calling system(git pull origin --rebase);', $output);
  }

  public function testShellAliasDrushRemote() {
    $options = array(
      'config' => UNISH_SANDBOX,
      'simulate' => NULL,
      'ssh-options' => '',
    );
    $this->drush('glopts', array(), $options, 'user@server/path/to/drupal#sitename');
    // $expected might be different on non unix platforms. We shall see.
    $expected = "Simulating backend invoke: ssh user@server 'drush  --simulate --nocolor --uri=sitename --root=/path/to/drupal --config=/tmp/drush-sandbox topic core-global-options --invoke 2>&1' 2>&1";
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
    $this->drush('pull', array('origin'), $options, 'user@server/path/to/drupal#sitename');
    // $expected might be different on non unix platforms. We shall see.
    $expected = "Calling proc_open(ssh  user@server 'cd /path/to/drupal && git pull origin --rebase');";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output, 'Expected remote shell alias to a bash command was built');
  }
}
