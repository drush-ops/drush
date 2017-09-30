<?php

namespace Unish;

/**
 * @group base
 * @group slow
 */
class SiteSetCommandCase extends CommandUnishTestCase
{

    /**
     * Test functionality of site set.
     */
    public function testSiteSet()
    {
        if ($this->is_windows()) {
            $this->markTestSkipped('Site-set not currently available on Windows.');
        }
        $sites = $this->setUpDrupal(2, true);
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
