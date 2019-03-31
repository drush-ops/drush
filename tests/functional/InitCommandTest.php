<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  Test to see if the `drush init` command does the
 *  setup that it is supposed to do.
 *
 *  @group base
 */
class InitCommandCase extends CommandUnishTestCase
{

    public function testInitCommand()
    {
        // Call `drush core-init`
        $this->drush('core-init', [], ['add-path' => true, 'yes' => null, 'no-ansi' => null]);
        $logOutput = $this->getErrorOutput();
        // First test to ensure that the command claimed to have made the expected progress
        $this->assertContains("Copied Drush bash customizations", $logOutput);
        $this->assertContains("Updated bash configuration file", $logOutput);

        // Next we test to see if there is evidence that those operations worked.
        $home = Path::join($this->getSandbox(), 'home');
        $this->assertFileExists("$home/.drush/drush.yml", $this->buildProcessMessage());
        $this->assertFileExists("$home/.drush/drush.bashrc", $this->buildProcessMessage());
        $this->assertFileExists("$home/.bashrc", $this->buildProcessMessage());

        // Check to see if the .bashrc file sources our drush.bashrc file,
        // and whether it adds the path to self::getDrush() to the $PATH
        $bashrc_contents = file_get_contents("$home/.bashrc");
        $this->assertContains('drush.bashrc', $bashrc_contents);

        $this->assertContains(Path::canonicalize(realpath(dirname(self::getDrush()))), $bashrc_contents);
    }
}
