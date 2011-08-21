<?php

/*
 * @file
 *   Tests for sitealias.inc
 */
class saCase extends Drush_TestCase {

  /*
   * Assure that site lists work as expected.
   * @todo Use --backend for structured return data. Depends on http://drupal.org/node/1043922
   */
  public function testSAList() {
    $this->setUpDrupal('dev');
    $this->setUpDrupal('stage');
    $eval = 'print "bon";';
    $options = array(
      'yes' => NULL,
      'root' => $this->sites['dev']['root'],
    );
    $this->drush('php-eval', array($eval), $options, "#dev,#stage");
    $expected = "You are about to execute 'php-eval print \"bon\";' on all of the following targets:
  #dev
  #stage
Continue?  (y/n): y
#dev   >> bon
#stage >> bon";
    $this->assertEquals($expected, $this->getOutput());
  }
}
