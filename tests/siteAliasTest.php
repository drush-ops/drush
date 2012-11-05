<?php

/*
 * @file
 *   Tests for sitealias.inc
 *
 * @group base
 */
class saCase extends Drush_CommandTestCase {

  /*
   * Assure that site lists work as expected.
   * @todo Use --backend for structured return data. Depends on http://drupal.org/node/1043922
   */
  public function testSAList() {
    $sites = $this->setUpDrupal(2);
    $subdirs = array_keys($sites);
    $eval = 'print "bon";';
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
    );
    foreach ($subdirs as $dir) {
      $dirs[] = "#$dir";
    }
    $this->drush('php-eval', array($eval), $options, implode(',', $dirs));
    $output = $this->getOutputAsList();
    // We sort the output, producing a screwy display, because we cannot
    // predict the order of the #dev >> and #stage >> lines, since they
    // are executed concurrently, and emitted in a non-deterministic order.
    sort($output);
    $expected = "  #dev
  #stage
#dev   >> bon
#stage >> bon
Continue?  (y/n): y
You are about to execute 'php-eval print \"bon\";' non-interactively (--yes forced) on all of the following targets:";
    $this->assertEquals($expected, implode("\n", $output));
  }
}
