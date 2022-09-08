<?php

namespace Unish;

use Symfony\Component\Yaml\Yaml;
use Drush\PathUtil\Path;

/**
 * Tests for core commands.
 *
 * @group commands
 */
class CoreTest extends UnishIntegrationTestCase
{
    public function testCoreRequirements()
    {
        $root = $this->webroot();
        $options = [
            'ignore' => 'cron,http requests,update,update_core,trusted_host_patterns', // no network access when running in tests, so ignore these
        ];
        // Verify that there are no severity 2 items in the status report
        $this->drush('core-requirements', [], $options + ['severity' => '2', 'pipe' => true]);
        $output = $this->getOutput();
        $this->assertEquals('', $output);

        // Verify the severity of some checks
        $this->drush('core-requirements', [], $options + ['format' => 'json', 'fields' => 'sid']);
        $loaded = $this->getOutputFromJSON();
        $expected = [
            'php' => ['sid' => '-1'],
            'php_memory_limit' => ['sid' => '-1'],
        ];
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $loaded);
            $this->assertEquals($value, $loaded[$key], "The $key requirement should have an expected value");
        }
    }

    public function testDrupalDirectory()
    {
        $root = $this->webroot();
        $sitewide = $this->drupalSitewideDirectory();

        if ($this->isWindows()) {
            $this->markTestSkipped('Windows escpaping woes.');
        }

        $this->drush('drupal-directory', ['%files']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/sites/default/files'), $output);

        $this->drush('drupal-directory', ['%modules']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, $sitewide . '/modules'), $output);

        $this->drush('pm-install', ['drush_empty_module']);
        $this->drush('theme-enable', ['drush_empty_theme']);

        $this->drush('drupal-directory', ['drush_empty_module']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/modules/unish/drush_empty_module'), $output);

        $this->drush('drupal-directory', ['drush_empty_theme']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/themes/unish/drush_empty_theme'), $output);
    }

    public function testRoute()
    {
        $this->drush('route', [], ['format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('user.login', $json);
        $this->assertSame('/user/login', $json['user.login']);
        $this->drush('route', [], ['path' => '/user/login', 'format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertSame('/user/login', $json['path']);
        $this->assertSame('user.login', $json['name']);
        $this->assertSame('\Drupal\user\Form\UserLoginForm', $json['defaults']['_form']);
        $this->assertSame("FALSE", $json['requirements']['_user_is_logged_in']);
        $this->assertSame('access_check.user.login_status', $json['options']['_access_checks'][0]);

        $this->drush('route', [], ['name' => 'user.login', 'format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertSame('/user/login', $json['path']);
    }
}
