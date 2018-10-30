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
    public function testSiteSelectionViaCwd()
    {
        $cwd = getcwd();
        $root = $this->webroot();
        foreach (['dev', 'stage'] as $uri) {
            $conf_dir = $root . '/sites/' . $uri;
            // We will chdir to the directory that contains settings.php
            // and ensure that we can bootstrap the selected site from here.
            chdir($conf_dir);
            $options['uri'] = 'OMIT'; // A special value which causes --uri to not be specified.
            $this->drush('core-status', [], $options);
            $output = $this->getOutput();
            $output = preg_replace('#  *#', ' ', $output);
            $this->assertContains('Database : Connected', $output);
            $this->assertContains("Site path : sites/$uri", $output);
        }
        chdir($cwd);
    }
}