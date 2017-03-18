<?php

namespace Unish;

/**
 * @group base
 */
class completeCase extends CommandUnishTestCase {
  /**
   * Write a config file that contains our configuration file.
   */
  static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    $contents = "
      <?php

      \$options['shell-aliases'] = array(
        'uninstall' => 'pm-uninstall',
      );
    ";
    file_put_contents(self::getSandbox() . '/drushrc.php', trim($contents));
  }

  

  public function testComplete() {
    if ($this->is_windows()) {
      $this->markTestSkipped('Complete tests not fully working nor needed on Windows.');
    }

    // We copy our completetest commandfile into our path.
    // We cannot use --include since complete deliberately avoids Drush command dispatch.
    copy(__DIR__ . '/completetest.drush.inc', getenv('HOME') . '/.drush/completetest.drush.inc');

    $sites = $this->setUpDrupal(2);
    $env = key($sites);
    $root = $this->webroot();
    // We copy the unit test command into (only) our dev site, so we have a
    // detectable difference we can use to detect cache correctness between
    // sites.
    mkdir("$root/sites/$env/modules");
    copy(dirname(__FILE__) . '/completetestsite.drush.inc', "$root/sites/$env/modules/completetestsite.drush.inc");
    // Clear the cache, so it finds our test command.
    $this->drush('php-eval', array('drush_cache_clear_all();'), array(), '@' . $env);

    // Create a sample directory and file to test file/directory completion.
    mkdir("astronaut");
    mkdir("asteroid");
    mkdir("asteroid/ceres");
    mkdir("asteroid/chiron");
    touch('astronaut/aldrin.php');
    touch('astronaut/armstrong.php');
    touch('astronaut/yuri gagarin.php');
    touch('zodiac.php');
    touch('zodiac.txt');

    // Create directory for temporary debug logs.
    mkdir(self::getSandbox() . '/complete-debug');

    // Test cache clearing for global cache, which should affect all
    // environments. First clear the cache:
    $this->drush('php-eval', array('drush_complete_cache_clear();'));
    // Confirm we get cache rebuilds for runs both in and out of a site
    // which is expected since these should resolve to separate cache IDs.
    $this->verifyComplete('@dev aaaaaaaard-', 'aaaaaaaard-ant', 'aaaaaaaard-zebra', FALSE);
    $this->verifyComplete('aaaaaaaard-', 'aaaaaaaard-ant', 'aaaaaaaard-wolf', FALSE);
    // Next, rerun and check results to confirm cache IDs are generated
    // correctly on our fast bootstrap when returning the cached result.
    $this->verifyComplete('@dev aaaaaaaard-', 'aaaaaaaard-ant', 'aaaaaaaard-zebra');
    $this->verifyComplete('aaaaaaaard-', 'aaaaaaaard-ant', 'aaaaaaaard-wolf');

    // Test cache clearing for a completion type, which should be effective only
    // for current environment - i.e. a specific site should not be effected.
    $this->drush('php-eval', array('drush_complete_cache_clear("command-names");'));
    $this->verifyComplete('@dev aaaaaaaard-', 'aaaaaaaard-ant', 'aaaaaaaard-zebra');
    $this->verifyComplete('aaaaaaaard-', 'aaaaaaaard-ant', 'aaaaaaaard-wolf', FALSE);

    // Test cache clearing for a command specific completion type, which should
    // be effective only for current environment. Prime caches first.
    $this->verifyComplete('@dev aaaaaaaard a', 'aardvark', 'aardwolf', FALSE);
    $this->verifyComplete('aaaaaaaard a', 'aardvark', 'aardwolf', FALSE);
    $this->drush('php-eval', array('drush_complete_cache_clear("arguments", "aaaaaaaard");'));
    // We cleared the global cache for this argument, not the site specific
    // cache should still exist.
    $this->verifyComplete('@dev aaaaaaaard a', 'aardvark', 'aardwolf');
    $this->verifyComplete('aaaaaaaard a', 'aardvark', 'aardwolf', FALSE);

    // Test overall context sensitivity - almost all of these are cache hits.
    // No context (i.e. "drush <tab>"), should list aliases and commands.
    $this->verifyComplete('""', '@dev', 'zzzzzzzzebra');
    // Site alias alone.
    $this->verifyComplete('@', '@dev', '@stage');
    // Command alone.
    $this->verifyComplete('aaaaaaaa', 'aaaaaaaard', 'aaaaaaaard-wolf');
    // Command with single result.
    $this->verifyComplete('aaaaaaaard-v', 'aaaaaaaard-vark', 'aaaaaaaard-vark');
    // Command with no results should produce no output.
    $this->verifyComplete('dont-name-a-command-like-this', '', '');
    // Commands that start the same as another command (i.e. aaaaaaaard is a
    // valid command, but we should still list aaaaaaaardwolf when completing on
    // "aaaaaaaard").
    $this->verifyComplete('@dev aaaaaaaard', 'aaaaaaaard', 'aaaaaaaard-zebra');
    // Global option alone.
    // @todo changed second value since global options provided by Annotated commands are not yet recognized by drush_get_global_options().
    // @todo and then commented out with new Help since this code will soon be removed.
    // $this->verifyComplete('--n', '--no', '--notify-audio');
    // $this->verifyComplete('--n', '--no', '--nocolor');
    // Site alias + command.
    $this->verifyComplete('@dev aaaaaaaa', 'aaaaaaaard', 'aaaaaaaard-zebra');
    // Site alias + command, should allow no further site aliases or commands.
    $this->verifyComplete('@dev aaaaaaaard-wolf @', '', '', FALSE);
    $this->verifyComplete('@dev aaaaaaaard-wolf aaaaaaaa', '', '');
    // Command + command option.
    $this->verifyComplete('aaaaaaaard --', '--ears', '--nose');
    // Site alias + command + command option.
    $this->verifyComplete('@dev aaaaaaaard --', '--ears', '--nose');
    // Command + all arguments
    $this->verifyComplete('aaaaaaaard ""', 'aardvark', 'zebra');
    // Command + argument.
    $this->verifyComplete('aaaaaaaard a', 'aardvark', 'aardwolf');
    // Site alias + command + regular argument.
    // Note: this is checked implicitly by the argument cache testing above.

    // Site alias + command + file/directory argument tests.
    // Current directory substrings.
    // NOTE: This command arg has not been used yet, so cache miss is expected.
    $this->verifyComplete('php-script ""', 'asteroid/', 'zodiac.php', FALSE);
    $this->verifyComplete('php-script a', 'asteroid/', 'astronaut/');
    $this->verifyComplete('php-script ast', 'asteroid/', 'astronaut/');
    $this->verifyComplete('php-script aste', 'asteroid/', 'asteroid/');
    $this->verifyComplete('php-script asteroid', 'asteroid/', 'asteroid/');
    $this->verifyComplete('php-script asteroid/', 'ceres', 'chiron');
    $this->verifyComplete('php-script asteroid/ch', 'asteroid/chiron/', 'asteroid/chiron/');
    $this->verifyComplete('php-script astronaut/', 'aldrin.php', 'yuri gagarin.php');
    $this->verifyComplete('php-script astronaut/y', 'astronaut/yuri\ gagarin.php', 'astronaut/yuri\ gagarin.php');
    // Leading dot style current directory substrings.
    $this->verifyComplete('php-script .', './asteroid/', './zodiac.php');
    $this->verifyComplete('php-script ./', './asteroid/', './zodiac.php');
    $this->verifyComplete('php-script ./a', './asteroid/', './astronaut/');
    $this->verifyComplete('php-script ./ast', './asteroid/', './astronaut/');
    $this->verifyComplete('php-script ./aste', './asteroid/', './asteroid/');
    $this->verifyComplete('php-script ./asteroid', './asteroid/', './asteroid/');
    $this->verifyComplete('php-script ./asteroid/', 'ceres', 'chiron');
    $this->verifyComplete('php-script ./asteroid/ch', './asteroid/chiron/', './asteroid/chiron/');
    $this->verifyComplete('php-script ./astronaut/', 'aldrin.php', 'yuri gagarin.php');
    $this->verifyComplete('php-script ./astronaut/y', './astronaut/yuri\ gagarin.php', './astronaut/yuri\ gagarin.php');
    // Absolute path substrings.
    $path = getcwd();
    $this->verifyComplete('php-script ' . $path, $path . '/', $path . '/');
    $this->verifyComplete('php-script ' . $path . '/', 'asteroid', 'zodiac.php');
    $this->verifyComplete('php-script ' . $path . '/a', $path . '/asteroid', $path . '/astronaut');
    $this->verifyComplete('php-script ' . $path . '/ast', 'asteroid', 'astronaut');
    $this->verifyComplete('php-script ' . $path . '/aste', $path . '/asteroid/', $path . '/asteroid/');
    $this->verifyComplete('php-script ' . $path . '/asteroid', $path . '/asteroid/', $path . '/asteroid/');
    $this->verifyComplete('php-script ' . $path . '/asteroid/', $path . '/asteroid/ceres', $path . '/asteroid/chiron');
    $this->verifyComplete('php-script ' . $path . '/asteroid/ch', $path . '/asteroid/chiron/', $path . '/asteroid/chiron/');
    $this->verifyComplete('php-script ' . $path . '/astronaut/', 'aldrin.php', 'yuri gagarin.php');
    $this->verifyComplete('php-script ' . $path . '/astronaut/y', $path . '/astronaut/yuri\ gagarin.php', $path . '/astronaut/yuri\ gagarin.php');
    // Absolute via parent path substrings.
    $this->verifyComplete('php-script ' . $path . '/asteroid/../astronaut/', 'aldrin.php', 'yuri gagarin.php');
    $this->verifyComplete('php-script ' . $path . '/asteroid/../astronaut/y', $path . '/asteroid/../astronaut/yuri\ gagarin.php', $path . '/asteroid/../astronaut/yuri\ gagarin.php');
    // Parent directory path substrings.
    chdir('asteroid/chiron');
    $this->verifyComplete('php-script ../../astronaut/', 'aldrin.php', 'yuri gagarin.php');
    $this->verifyComplete('php-script ../../astronaut/y', '../../astronaut/yuri\ gagarin.php', '../../astronaut/yuri\ gagarin.php');
    chdir($path);
  }

  /**
   * Helper function to call completion and make common checks.
   *
   * @param $command
   *   The command line to attempt to complete.
   * @param $first
   *   String indicating the expected first completion suggestion.
   * @param $last
   *   String indicating the expected last completion suggestion.
   * @param bool $cache_hit
   *   Optional parameter, if TRUE or omitted the debug log is checked to
   *   ensure a cache hit on the last cache debug log entry, if FALSE then a
   *   cache miss is checked for.
   */
  function verifyComplete($command, $first, $last, $cache_hit = TRUE) {
    // We capture debug output to a separate file, so we can check for cache
    // hits/misses.
    $debug_file = tempnam(self::getSandbox() . '/complete-debug', 'complete-debug');
    // Commands should take the format:
    // drush --early=includes/complete.inc [--complete-debug] drush [@alias] [command]...
    $exec = sprintf('%s --early=includes/complete.inc --config=%s --complete-debug %s %s 2> %s', self::getDrush(), self::getSandbox() . '/drushrc.php', self::getDrush(), $command, $debug_file);
    $this->execute($exec);
    $result = $this->getOutputAsList();
    $actual = reset($result);
    $this->assertEquals("$command: (f) $first", "$command: (f) $actual");
    $actual = end($result);
    $this->assertEquals("$command: (l) $last", "$command: (l) $actual");
    // If checking for HIT, we ensure no MISS exists, if checking for MISS we
    // ensure no HIT exists. However, we exclude the first cache report, since
    // it is expected that the command-names cache (loaded when matching
    // command names) may sometimes be a HIT even when we are testing for a MISS
    // in the actual cache we are loading to complete against.
    $check_not_exist = 'HIT';
    if ($cache_hit) {
      $check_not_exist = 'MISS';
    }
    $contents = file_get_contents($debug_file);
    // Find the all cache messages of type "-complete-"
    preg_match_all("/Cache [A-Z]* cid:.*-complete-/", $contents, $matches);
    $contents = implode("\n", $matches[0]);
    $first_cache_pos = strpos($contents, 'Cache ') + 6;
    $this->assertFalse(strpos($contents, 'Cache ' . $check_not_exist . ' cid', $first_cache_pos));
    unlink($debug_file);
  }
}
