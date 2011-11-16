<?php

class completeCase extends Drush_CommandTestCase {
  public function testComplete() {
    $sites = $this->setUpDrupal(2);
    $env = key($sites);
    $root = $this->webroot();
    // We copy our test command into our dev site, so we have a difference we
    // can detect for cache correctness. We cannot use --include since complete
    // deliberately avoids drush command dispatch.
    mkdir("$root/sites/$env/modules");
    copy(dirname(__FILE__) . '/unit.drush.inc', "$root/sites/$env/modules/unit.drush.inc");
    // Clear the cache, so it finds our test command.
    $this->drush('php-eval', array('drush_cache_clear_all();'), array(), '@' . $env);

    // Create a sample directory and file to test file/directory completion.
    mkdir("aardvark");
    touch('aard wolf.tar.gz');

    // Create directory for temporary debug logs.
    mkdir(UNISH_SANDBOX . '/complete-debug');

    // Test cache clearing for global cache, which should affect all
    // environments. First clear the cache:
    $this->drush('php-eval', array('drush_complete_cache_clear();'));
    // Confirm we get cache rebuilds for runs both in and out of a site
    // which is expected since these should resolve to separate cache IDs.
    $this->verifyComplete('@dev uni', 'uninstall', 'unit-batch', FALSE);
    $this->verifyComplete('uni', 'uninstall', 'uninstall', FALSE);
    // Next, rerun and check results to confirm cache IDs are generated
    // correctly on our fast bootstrap when returning the cached result.
    $this->verifyComplete('@dev uni', 'uninstall', 'unit-batch');
    $this->verifyComplete('uni', 'uninstall', 'uninstall');

    // Test cache clearing for a completion type, which should be effective only
    // for current environment - i.e. a specific site should not be effected.
    $this->drush('php-eval', array('drush_complete_cache_clear("command-names");'));
    $this->verifyComplete('@dev uni', 'uninstall', 'unit-batch');
    $this->verifyComplete('uni', 'uninstall', 'uninstall', FALSE);

    // Test cache clearing for a command specific completion type, which should
    // be effective only for current environment. Prime caches first.
    $this->verifyComplete('@dev topic docs-c', 'docs-configuration', 'docs-context', FALSE);
    $this->verifyComplete('topic docs-c', 'docs-configuration', 'docs-context', FALSE);
    $this->drush('php-eval', array('drush_complete_cache_clear("arguments", "topic");'));
    // We cleared the global cache for this argument, not the site specific
    // cache should still exist.
    $this->verifyComplete('@dev topic docs-c', 'docs-configuration', 'docs-context');
    $this->verifyComplete('topic docs-c', 'docs-configuration', 'docs-context', FALSE);

    // Test overall context sensitivity - almost all of these are cache hits.
    // No context (i.e. "drush <tab>"), should list aliases and commands.
    $this->verifyComplete("''", '@none', 'wd');
    // Site alias alone.
    $this->verifyComplete('@', '@none', '@dev');
    // Command alone.
    $this->verifyComplete('d', 'drupal-directory', 'download');
    // Command with single result.
    $this->verifyComplete('core-t', 'core-topic', 'core-topic');
    // Command with no results should produce no output.
    $this->verifyComplete('dont-name-a-command-like-this', '', '');
    // Commands that start the same as another command (i.e. unit is a valid
    // command, but we should still list unit-eval and unit-invoke when
    // completing on "unit").
    $this->verifyComplete('@dev unit', 'unit', 'unit-batch');
    // Global option alone.
    $this->verifyComplete('--n', '--no', '--nocolor');
    // Site alias + command.
    $this->verifyComplete('@dev d', 'drupal-directory', 'download');
    // Site alias + command, should allow no further site aliases or commands.
    $this->verifyComplete('@dev topic @', '', '');
    $this->verifyComplete('@dev topic topi', '', '');
    // Command + command option.
    $this->verifyComplete('dl --', '--destination', '--gitsubmoduleaddparams');
    // Site alias + command + command option.
    $this->verifyComplete('@dev dl --', '--destination', '--gitsubmoduleaddparams');
    // Command + argument.
    $this->verifyComplete('topic docs-c', 'docs-configuration', 'docs-context');
    // Site alias + command + regular argument.
    // Note: this is checked implicitly by the argument cache testing above.
    // Site alias + command + file/directory argument. This is a command
    // argument we have not used so far, so a cache miss is expected.
    $this->verifyComplete('archive-restore aard', 'aardvark/', 'aard wolf.tar.gz', FALSE);
    // Site alias + command + file/directory argument with quoting.
    $this->verifyComplete('archive-restore aard\ w', 'aard\ wolf.tar.gz', 'aard\ wolf.tar.gz');
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
    $debug_file = tempnam(UNISH_SANDBOX . '/complete-debug', 'complete-debug');
    // Commands should take the format:
    // drush --early=includes/complete.inc [--complete-debug] drush [@alias] [command]...
    $exec = sprintf('%s --early=includes/complete.inc --complete-debug %s %s 2> %s', UNISH_DRUSH, UNISH_DRUSH, $command, $debug_file);
    $this->execute($exec);
    $result = $this->getOutputAsList();
    $actual = reset($result);
    $this->assertEquals("$command: $first", "$command: $actual");
    $actual = end($result);
    $this->assertEquals("$command: $last", "$command: $actual");
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
    $first_cache_pos = strpos($contents, 'Cache ') + 6;
    $this->assertFalse(strpos($contents, 'Cache ' . $check_not_exist . ' cid', $first_cache_pos));
    unlink($debug_file);
  }
}
