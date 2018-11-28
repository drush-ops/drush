<?php

namespace Unish;

use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Tests for core commands.
 *
 * @group commands
 */
class CoreTest extends UnishIntegrationTestCase
{
    public function testDrupalDirectory()
    {
        $root = $this->webroot();
        $sitewide = $this->drupalSitewideDirectory();
        $this->drush('drupal-directory', ['%files']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/sites/dev/files'), $output);

        $this->drush('drupal-directory', ['%modules']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, $sitewide . '/modules'), $output);

        $this->drush('pm-enable', ['devel']);
        $this->drush('theme-enable', ['empty_theme']);

        $this->drush('drupal-directory', ['devel']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/modules/unish/devel'), $output);

        $this->drush('drupal-directory', ['empty_theme']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/themes/unish/empty_theme'), $output);
    }
}
