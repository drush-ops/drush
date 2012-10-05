<?php

/**
 * @group base
 */
class siteSetCommandCase extends Drush_CommandTestCase {

  function testSiteSet() {
    if ($this->is_windows()) {
      $this->markTestSkipped('Site-set not currently available on Windows.');
    }
    $sites = $this->setUpDrupal(1, TRUE);
    $site_names = array_keys($sites);
    $alias = '@' . $site_names[0];

    $this->drush('ev', array("drush_invoke('site-set', '$alias'); print drush_sitealias_site_get();"));
    $output = $this->getOutput();
    $this->assertEquals("Site set to $alias\n$alias", $output);

    $this->drush('site-set', array($alias));
    $expected = 'Site set to ' . $alias;
    $output = $this->getOutput();

    $this->assertEquals($expected, $output);

    $this->drush('site-set', array());
    $output = $this->getOutput();
    $this->assertEquals('Site set to @none', $output);

    $this->drush('ev', array("drush_invoke('site-set', '$alias'); print drush_sitealias_site_get();"));
    $output = $this->getOutput();
    $this->assertEquals("Site set to $alias
$alias", $output);

    $this->drush('ev', array("drush_invoke('site-set', '$alias'); drush_invoke('site-set', '@none'); drush_invoke('site-set', '-'); print drush_sitealias_site_get();"));
    $output = $this->getOutput();
    $this->assertEquals("Site set to $alias
Site set to @none
Site set to $alias
$alias", $output);
  }
}
