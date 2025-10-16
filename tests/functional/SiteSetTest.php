<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\SiteCommands;

/**
 * @group base
 * @group slow
 */
class SiteSetTest extends CommandUnishTestCase
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
            $this->drush(SiteCommands::SET, [$site_alias]);
            $output = $this->getErrorOutput();
            $this->assertStringContainsString('[success] Site set to ' . $site_alias, $output);
        }

        // Test setting the site to the special @none alias.
        $this->drush(SiteCommands::SET, ['@none']);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('[success] Site unset.', $output);

        // Alternative to '@none'.
        $this->drush(SiteCommands::SET, ['']);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('[success] Site unset.', $output);

        // @todo Fix this toggling.
        $this->markTestSkipped('Inexplicably fails on TravisCI but not locally.');

        // Toggle between the previous set alias and back again.
        $this->drush(SiteCommands::SET, ['-']);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('[success] Site set to ' . $site_aliases[0], $output);
        $this->drush(SiteCommands::SET, ['-']);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('[success] Site set to ' . $site_aliases[1], $output);
    }
}
