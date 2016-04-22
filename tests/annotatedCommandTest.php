<?php

namespace Unish;

/**
 * @group base
 */
class annotatedCommandCase extends CommandUnishTestCase {
  public function testExecute() {
    $sites = $this->setUpDrupal(1, TRUE);
    $uri = key($sites);
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'yes' => NULL,
    );

    // Copy the 'woot' module over to the Drupal site we just set up.
    $this->setupModulesForTests($root);

    // Enable out module. This will also clear the commandfile cache.
    $this->drush('pm-enable', array('woot'), $options, NULL, NULL, self::EXIT_SUCCESS);

    // drush woot --help
    $this->drush('woot', array(), $options + ['help' => NULL], NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertContains('Woot mightily.', $output);
    $this->assertContains('Aliases: wt', $output);

    // drush help woot
    $this->drush('help', array('woot'), $options, NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertContains('Woot mightily.', $output);

    // drush woot
    $this->drush('woot', array(), $options, NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertEquals('Woot!', $output);

    // drush my-cat --help
    $this->drush('my-cat', array(), $options + ['help' => NULL], NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertContains('This is the my-cat command', $output);
    $this->assertContains('bet alpha --flip', $output);
    $this->assertContains('The first parameter', $output);
    $this->assertContains('The other parameter', $output);
    $this->assertContains('Whether or not the second parameter', $output);
    $this->assertContains('Aliases: c', $output);

    // drush help my-cat
    $this->drush('help', array('my-cat'), $options, NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertContains('This is the my-cat command', $output);

    // drush my-cat bet alpha --flip
    $this->drush('my-cat', array('bet', 'alpha'), $options + ['flip' => NULL], NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertEquals('alphabet', $output);

    // drush woot --help with the 'woot' module ignored
    $this->drush('woot', array(), $options + ['help' => NULL, 'ignored-modules' => 'woot'], NULL, NULL, self::EXIT_ERROR);

    // drush my-cat bet alpha --flip
    $this->drush('my-cat', array('bet', 'alpha'), $options + ['flip' => NULL, 'ignored-modules' => 'woot'], NULL, NULL, self::EXIT_ERROR);

    // Disable the woot module to avoid cross-contamination of the Drupal
    // test site's database.
    if (UNISH_DRUPAL_MAJOR_VERSION == 8) {
      $this->drush('pm-uninstall', array('woot'), $options, NULL, NULL, self::EXIT_SUCCESS);
    }
    else {
      $this->drush('pm-disable', array('woot'), $options, NULL, NULL, self::EXIT_SUCCESS);
    }
    // Also kill the Drush cache so that our 'woot' command is not cached.
    $this->drush('cache-clear', array('drush'), $options, NULL, NULL, self::EXIT_SUCCESS);
  }

  public function setupModulesForTests($root) {
    $wootModule = __DIR__ . '/resources/modules/d' . UNISH_DRUPAL_MAJOR_VERSION . '/woot';
    $modulesDir = "$root/sites/all/modules";
    $this->mkdir($modulesDir);
    \symlink($wootModule, "$modulesDir/woot");
    if ((UNISH_DRUPAL_MAJOR_VERSION < 8) && !file_exists("$wootModule/Command/WootCommands.php")) {
      $woot8Module = __DIR__ . '/resources/modules/d8/woot';
      \symlink("$woot8Module/src/Command/WootCommands.php", "$wootModule/Command/WootCommands.php");
    }
  }
}
