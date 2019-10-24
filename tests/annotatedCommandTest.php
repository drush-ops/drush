<?php

namespace Unish;

/**
 * @group base
 */
class annotatedCommandCase extends CommandUnishTestCase {

  public function testGlobal() {
    $globalExtensions = $this->setupGlobalExtensionsForTests();

    $options = [];

    // We modified the set of available Drush commands; we need to clear the Drush command cache
    $this->drush('cc', array('drush'), $options);

    // drush foobar
    $options['include'] = "$globalExtensions";
    $this->drush('foobar', array(), $options);
    $output = $this->getOutput();
    $this->assertEquals('baz', $output);

    // Clear the Drush command cache again and test again with new includes
    $this->drush('cc', array('drush'), $options);

    // drush foobar again, except include the 'Commands' folder when passing --include
    $options['include'] = "$globalExtensions/Commands";
    $this->drush('foobar', array(), $options);
    $output = $this->getOutput();
    $this->assertEquals('baz', $output);
  }

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

    // These are not good asserts, but for the purposes of isolation....
    $targetDir = $root . DIRECTORY_SEPARATOR . $this->drupalSitewideDirectory() . '/modules/woot';
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
        $commandFile = $targetDir . "/src/Commands/WootCommands.php";
    } else {
        $commandFile = $targetDir . "/Commands/WootCommands.php";
    }
    $this->assertFileExists(dirname(dirname(dirname($commandFile))));
    $this->assertFileExists(dirname(dirname($commandFile)));
    $this->assertFileExists(dirname($commandFile));
    $this->assertFileExists($commandFile);

    // Enable out module. This will also clear the commandfile cache.
    $this->drush('pm-enable', array('woot'), $options);

    // In theory this is not necessary, but this test keeps failing.
    $this->drush('cc', array('drush'), $options);

    // drush woot --help
    $this->drush('woot', array(), $options + ['help' => NULL]);
    $output = $this->getOutput();
    $this->assertContains('Woot mightily.', $output);
    $this->assertContains('Aliases: wt', $output);

    // drush help woot
    $this->drush('help', array('woot'), $options);
    $output = $this->getOutput();
    $this->assertContains('Woot mightily.', $output);

    // drush woot
    $this->drush('woot', array(), $options);
    $output = $this->getOutput();
    $this->assertEquals('Woot!', $output);

    // drush my-cat --help
    $this->drush('my-cat', array(), $options + ['help' => NULL]);
    $output = $this->getOutput();
    $this->assertContains('This is the my-cat command', $output);
    $this->assertContains('bet alpha --flip', $output);
    $this->assertContains('The first parameter', $output);
    $this->assertContains('The other parameter', $output);
    $this->assertContains('Whether or not the second parameter', $output);
    $this->assertContains('Aliases: c', $output);

    // drush help my-cat
    $this->drush('help', array('my-cat'), $options);
    $output = $this->getOutput();
    $this->assertContains('This is the my-cat command', $output);

    // drush my-cat bet alpha --flip
    $this->drush('my-cat', array('bet', 'alpha'), $options + ['flip' => NULL]);
    $output = $this->getOutput();
    $this->assertEquals('alphabet', $output);

    // drush woot --help with the 'woot' module ignored
    $this->drush('woot', array(), $options + ['help' => NULL, 'ignored-modules' => 'woot'], NULL, NULL, self::EXIT_ERROR);

    // drush my-cat bet alpha --flip
    $this->drush('my-cat', array('bet', 'alpha'), $options + ['flip' => NULL, 'ignored-modules' => 'woot'], NULL, NULL, self::EXIT_ERROR);

    $this->drush('try-formatters', array(), $options);
    $output = $this->getOutput();
    $expected = <<<EOT
 ------ ------ -------
  I      II     III
 ------ ------ -------
  One    Two    Three
  Eins   Zwei   Drei
  Ichi   Ni     San
  Uno    Dos    Tres
 ------ ------ -------
EOT;
    $this->assertEquals(trim(preg_replace('#[ \n]+#', ' ', $expected)), trim(preg_replace('#[ \n]+#', ' ', $output)));

    $this->drush('try-formatters --format=yaml --fields=III,II', array(), $options, NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $expected = <<<EOT
en:
  third: Three
  second: Two
de:
  third: Drei
  second: Zwei
jp:
  third: San
  second: Ni
es:
  third: Tres
  second: Dos
EOT;
    $this->assertEquals($expected, $output);

    $this->drush('try-formatters', array(), $options + ['backend' => NULL]);
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];
    $expected = <<<EOT
{"en":{"first":"One","second":"Two","third":"Three"},"de":{"first":"Eins","second":"Zwei","third":"Drei"},"jp":{"first":"Ichi","second":"Ni","third":"San"},"es":{"first":"Uno","second":"Dos","third":"Tres"}}
EOT;
    $this->assertEquals($expected, json_encode($data));

    // drush try-formatters --help
    $this->drush('try-formatters', array(), $options + ['help' => NULL], NULL, NULL, self::EXIT_SUCCESS);
    $output = $this->getOutput();
    $this->assertContains('Demonstrate formatters', $output);
    $this->assertContains('try:formatters --fields=first,third', $output);
    $this->assertContains('try:formatters --fields=III,II', $output);
    $this->assertContains('--fields=<first, second, third>', $output);
    $this->assertContains('Fields to output. All available', $output);
    $this->assertContains('--format=<table>', $output);
    $this->assertContains('Select output format. Available:', $output);
    $this->assertContains('Aliases: try-formatters', $output);

    // If we are running Drupal version 8 or later, then also check to
    // see if the demo:greet and annotated:greet commands are available.
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
        $this->drush('demo:greet symfony', array(), $options, NULL, NULL, self::EXIT_SUCCESS);
        $output = $this->getOutput();
        $this->assertEquals('Hello symfony', $output);

        $this->drush('annotated:greet symfony', array(), $options, NULL, NULL, self::EXIT_SUCCESS);
        $output = $this->getOutput();
        $this->assertEquals('Hello symfony', $output);
    }

    // Flush the Drush cache so that our 'woot' command is not cached.
    $this->drush('cache-clear', array('drush'), $options, NULL, NULL, self::EXIT_SUCCESS);
  }

  public function setupGlobalExtensionsForTests() {
    $globalExtension = __DIR__ . '/resources/global-includes';
    $targetDir = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'global-includes';
    $this->mkdir($targetDir);
    $this->recursive_copy($globalExtension, $targetDir);
    return $targetDir;
  }

  public function setupModulesForTests($root) {
    $wootMajor = UNISH_DRUPAL_MAJOR_VERSION == '9' ? '8' : UNISH_DRUPAL_MAJOR_VERSION;
    $wootModule = __DIR__ . '/resources/modules/d' . $wootMajor . '/woot';
    $targetDir = $root . DIRECTORY_SEPARATOR . $this->drupalSitewideDirectory() . '/modules/woot';
    $this->mkdir($targetDir);
    $this->recursive_copy($wootModule, $targetDir);
  }
}
