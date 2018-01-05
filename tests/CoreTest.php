<?php

namespace Unish;

use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Tests for core commands.
 *
 * @group commands
 */
class CoreCase extends CommandUnishTestCase
{

    public function setUp()
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(2, true);
        }
    }

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

    public function testCoreRequirements()
    {
        $root = $this->webroot();
        $options = [
        'pipe' => null,
        'ignore' => 'cron,http requests,update,update_core,trusted_host_patterns', // no network access when running in tests, so ignore these
        // 'strict' => 0, // invoke from script: do not verify options
        ];
        // Verify that there are no severity 2 items in the status report
        $this->drush('core-requirements', [], $options + ['severity' => '2']);
        $output = $this->getOutput();
        $this->assertEquals('', $output);

        $this->drush('core-requirements', [], $options);
        $loaded = $this->getOutputFromJSON();
        // Pick a subset that are valid for D6/D7/D8.
        $expected = [
        // 'install_profile' => -1,
        // 'node_access' => -1,
        'php' => -1,
        // 'php_extensions' => -1,
        'php_memory_limit' => -1,
        'php_register_globals' => -1,
        'settings.php' => -1,
        ];
        foreach ($expected as $key => $value) {
            if (isset($loaded->$key)) {
                $this->assertEquals($value, $loaded->$key->sid);
            }
        }
    }

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

    public function testOptionsUri()
    {
        // Put a yml file in the drush folder.
        $drush_config_file = Path::join($this->getSut(), 'drush', 'drush.yml');
        $test_uri = 'http://test.uri';
        $options_with_uri = [
        'options' => [
        'uri' => $test_uri,
        ],
        ];
        $options = [
        'format' => 'json',
        'uri' => 'OMIT', // A special value which causes --uri to not be specified.
        ];
        file_put_contents($drush_config_file, Yaml::dump($options_with_uri, PHP_INT_MAX, 2));
        $this->drush('core-status', [], $options);
        unlink($drush_config_file);
        $output = $this->getOutputFromJSON();
        $this->assertEquals($test_uri, $output->uri);
    }

    public function testRecursiveConfigLoading()
    {
        // Put a yml file in the drush folder.
        $drush_config_file = Path::join($this->getSut(), 'drush', 'drush.yml');
        $a_drush_config_file = Path::join($this->getSut(), 'drush', 'a.drush.yml');
        $b_drush_config_file = Path::join($this->getSut(), 'drush', 'b.drush.yml');
        $test_uri = 'http://test.uri';
        // Set up multiple drush.yml files that include one another to test
        // potential infinite loop.
        $drush_yml_options = [
          'drush' => [
            'paths' => [
              'config' => [
                $a_drush_config_file,
              ],
            ],
          ],
        ];
        $a_drush_yml_options = [
          'drush' => [
            'paths' => [
              'config' => [
                $b_drush_config_file,
              ],
            ],
          ],
        ];
        $b_drush_yml_options = [
          'drush' => [
            'paths' => [
              'config' => [
                $a_drush_config_file,
              ],
            ],
          ],
          'options' => [
            'uri' => $test_uri,
          ],
        ];
        $command_options = [
          'format' => 'json',
          'uri' => 'OMIT', // A special value which causes --uri to not be specified.
        ];
        file_put_contents($drush_config_file, Yaml::dump($drush_yml_options, PHP_INT_MAX, 2));
        file_put_contents($a_drush_config_file, Yaml::dump($a_drush_yml_options, PHP_INT_MAX, 2));
        file_put_contents($b_drush_config_file, Yaml::dump($b_drush_yml_options, PHP_INT_MAX, 2));
        $this->drush('core-status', [], $command_options, null, $this->getSut());
        unlink($drush_config_file);
        unlink($a_drush_config_file);
        unlink($b_drush_config_file);
        $output = $this->getOutputFromJSON();
        $drush_conf_as_string = print_r($output->{'drush-conf'}, true);
        $this->assertContains($a_drush_config_file, $output->{'drush-conf'}, "Loaded drush config files are: " . $drush_conf_as_string);
        $this->assertContains($b_drush_config_file, $output->{'drush-conf'}, "Loaded drush config files are: " . $drush_conf_as_string);
        $this->assertEquals($test_uri, $output->uri);
    }
}
