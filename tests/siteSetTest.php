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
        $this->assertCount(2, $site_names, 'Has 2 drupal sites setup');

        // Test changing aliases.
        foreach ($site_names as $site_name) {
            $this->drush('site:set', ['@' . $site_name]);
            $output = $this->getErrorOutput();
            $this->assertEquals('[success] Site set to @' . $site_name, $output);
        }

        // Test setting the site to the special @none alias.
        $this->drush('site:set', ['@none']);
        $output = $this->getErrorOutput();
        $this->assertEquals('[success] Site unset.', $output);

        // Alternative to '@none'.
        $this->drush('site:set', ['']);
        $output = $this->getErrorOutput();
        $this->assertEquals('[success] Site unset.', $output);

        // @todo Fix this toggling.
        $this->markTestSkipped('Inexplicably fails on TravisCI but not locally.');

        // Toggle between the previous set alias and back again.
        $this->drush('site:set', ['-']);
        $output = $this->getErrorOutput();
        $this->assertEquals('[success] Site set to @' . $site_names[0], $output);
        $this->drush('site:set', ['-']);
        $output = $this->getErrorOutput();
        $this->assertEquals('[success] Site set to @' . $site_names[1], $output);
    }
}
