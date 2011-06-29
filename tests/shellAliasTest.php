<?php

/*
 * @file
 *   Tests for Shell aliases.
 */
class shellAliasesCase extends Drush_CommandTestCase {

  /*
   * Assure that shell aliases expand as expected.
   */
  public function testShellAliases() {
    $contents = "
      <?php

      \$options['shell-aliases'] = array('glopts' => 'topic core-global-options');
    ";
    file_put_contents(UNISH_SANDBOX . '/drushrc.php', $contents);
    $options = array(
      'config' => UNISH_SANDBOX,
    );
    $this->drush('glopts', array('php_configuration'), $options);
    $output = $this->getOutput();
    $this->assertContains('These options are applicable to most drush commands.', $output);
  }
}