<?php

class siteSetCommandCase extends Drush_CommandTestCase {

  function testSiteSet() {
    $sites = $this->setUpDrupal(1, TRUE);
    $site_names = array_keys($sites);
    $alias = '@' . $site_names[0];

    $this->drush('site-set', array($alias));
    $expected = 'Site set to ' . $alias;
    $output = $this->getOutput();

    $this->assertEquals($expected, $output);

    $this->drush('ev', array('print drush_sitealias_site_get()'));
    $output = $this->getOutput();

    $this->assertEquals($alias, $output);
  }
}
