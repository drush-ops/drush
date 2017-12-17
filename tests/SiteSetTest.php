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
        if ($this->isWindows()) {
            $this->markTestSkipped('Site-set not currently available on Windows.');
        }
        $sites = $this->setUpDrupal(2, true);
        $site_aliases = $this->getAliases();
        $this->assertCount(2, $site_aliases);

        // Test changing aliases.
        foreach ($site_aliases as $site_alias) {
            $this->drush('site:set', [$site_alias]);
            $output = $this->getErrorOutput();
            $this->assertEquals('[success] Site set to ' . $site_alias, $output);
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
        $this->assertEquals('[success] Site set to ' . $site_aliases[0], $output);
        $this->drush('site:set', ['-']);
        $output = $this->getErrorOutput();
        $this->assertEquals('[success] Site set to ' . $site_aliases[1], $output);
    }
}
