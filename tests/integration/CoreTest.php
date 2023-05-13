<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\CoreCommands;
use Drush\Commands\core\DrupalDirectoryCommands;
use Drush\Commands\core\DrupalCommands;
use Drush\Commands\pm\PmCommands;
use Drush\Commands\pm\ThemeCommands;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

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
        $this->drush(DrupalCommands::REQUIREMENTS, [], $options + ['severity' => '2', 'format' => 'json']);
        $output = $this->getOutput();
        $this->assertEquals('[]', $output);

        // Verify the severity of some checks
        $this->drush(DrupalCommands::REQUIREMENTS, [], $options + ['format' => 'json', 'fields' => 'sid']);
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
            $this->markTestSkipped('Windows escaping woes.');
        }

        $this->drush(DrupalDirectoryCommands::DIRECTORY, ['%files']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/sites/default/files'), $output);

        $this->drush(DrupalDirectoryCommands::DIRECTORY, ['%modules']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, $sitewide . '/modules'), $output);

        $this->drush(PmCommands::INSTALL, ['drush_empty_module']);
        $this->drush(ThemeCommands::INSTALL, ['drush_empty_theme']);

        $this->drush(DrupalDirectoryCommands::DIRECTORY, ['drush_empty_module']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/modules/unish/drush_empty_module'), $output);

        $this->drush(DrupalDirectoryCommands::DIRECTORY, ['drush_empty_theme']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/themes/unish/drush_empty_theme'), $output);
    }

    public function testRoute()
    {
        $this->drush(DrupalCommands::ROUTE, [], ['format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('user.login', $json);
        $this->assertSame('/user/login', $json['user.login']);
        $this->drush(DrupalCommands::ROUTE, [], ['path' => '/user/login', 'format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertSame('/user/login', $json['path']);
        $this->assertSame('user.login', $json['name']);
        $this->assertSame('\Drupal\user\Form\UserLoginForm', $json['defaults']['_form']);
        $this->assertSame("FALSE", $json['requirements']['_user_is_logged_in']);
        $this->assertSame('access_check.user.login_status', $json['options']['_access_checks'][0]);

        $this->drush(DrupalCommands::ROUTE, [], ['name' => 'user.login', 'format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertSame('/user/login', $json['path']);
    }
}
