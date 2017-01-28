<?php

namespace Unish;

/**
 * @group base
 * @group slow
 */
class siteSetCommandCase extends CommandUnishTestCase {

  function testSiteSet() {
    if ($this->is_windows()) {
      $this->markTestSkipped('Site-set not currently available on Windows.');
    }
    $sites = $this->setUpDrupal(1, TRUE);
    $site_names = array_keys($sites);
    $alias = '@' . $site_names[0];

    $this->drush('ev', array("drush_invoke('site-set', '$alias'); print drush_sitealias_site_get();"));
    $output = $this->getOutput();
    $this->assertEquals($alias, $output);

    $this->drush('ev', array("drush_invoke('site-set', '@none'); print drush_sitealias_site_get();"));
    $output = $this->getOutput();
    $this->assertEquals('', $output);
  }
}
