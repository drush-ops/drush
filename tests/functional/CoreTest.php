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
        // Test whether a URI in a config file resolves correctly, and test
        // various URI values for their expected Site URI and path.
        $drush_config_file = Path::join($this->webrootSlashDrush(), 'drush.yml');
        $command_options = [
        'format' => 'json',
        'uri' => 'OMIT', // A special value which causes --uri to not be specified.
        ];
        foreach ([
                   'test.uri' => ['http://test.uri', 'sites/dev'],
                   'test.uri/' => ['http://test.uri/', 'sites/dev'],
                   'test.uri/subpath' => ['http://test.uri/subpath', 'sites/stage'],
                   'test.uri/subpath/' => ['http://test.uri/subpath/', 'sites/stage'],
                   'http://test.uri' => ['http://test.uri', 'sites/dev'],
                   'http://test.uri/' => ['http://test.uri/', 'sites/dev'],
                   'http://test.uri/subpath' => ['http://test.uri/subpath', 'sites/stage'],
                   'http://test.uri/subpath/' => ['http://test.uri/subpath/', 'sites/stage'],
                   'https://test.uri' => ['https://test.uri', 'sites/dev'],
                   'https://test.uri/' => ['https://test.uri/', 'sites/dev'],
                   'https://test.uri/subpath' => ['https://test.uri/subpath', 'sites/stage'],
                   'https://test.uri/subpath/' => ['https://test.uri/subpath/', 'sites/stage'],
                 ] as $test_uri => $expected) {
            // Put a yml file in the drush folder.
            $config_options = [
              'options' => [
                'uri' => $test_uri,
              ],
            ];
            file_put_contents($drush_config_file, Yaml::dump($config_options, PHP_INT_MAX, 2));
            $this->drush('core-status', [], $command_options);
            unlink($drush_config_file);
            $output = $this->getOutputFromJSON();
            // Include the test URI, for some context in errors.
            $this->assertEquals([$test_uri => $expected], [$test_uri => [$output['uri'], $output['site']]]);
        }
    }

    public function testOptionsUriRequestUrl()
    {
        // Test whether a URI in a config file resolves correctly, and test
        // various URI values for their expected Site URI and path.
        $drush_config_file = Path::join($this->webrootSlashDrush(), 'drush.yml');
        $command_options = [
        'uri' => 'OMIT', // A special value which causes --uri to not be specified.
        ];
        foreach ([
                   'test.uri' => 'http://test.uri',
                   'test.uri/' => 'http://test.uri',
                   'test.uri/subpath' => 'http://test.uri/subpath',
                   'test.uri/subpath/' => 'http://test.uri/subpath',
                   'http://test.uri' => 'http://test.uri',
                   'http://test.uri/' => 'http://test.uri',
                   'http://test.uri/subpath' => 'http://test.uri/subpath',
                   'http://test.uri/subpath/' => 'http://test.uri/subpath',
                   'https://test.uri' => 'https://test.uri',
                   'https://test.uri/' => 'https://test.uri',
                   'https://test.uri/subpath' => 'https://test.uri/subpath',
                   'https://test.uri/subpath/' => 'https://test.uri/subpath',
                 ] as $test_uri => $expected) {
            // Put a yml file in the drush folder.
            $config_options = [
              'options' => [
                'uri' => $test_uri,
              ],
            ];
            file_put_contents($drush_config_file, Yaml::dump($config_options, PHP_INT_MAX, 2));
            $this->drush('unit-eval', ["return Drupal::request()->getScheme() . '://' . Drupal::request()->getHost() . Drupal::request()->getBaseUrl();"], $command_options);
            unlink($drush_config_file);
            $output = $this->getOutputRaw();
            // Include the test URI, for some context in errors.
            $i=10;
            $this->assertEquals([$test_uri => $expected], [$test_uri => trim($output)]);
        }
    }


    public function testRecursiveConfigLoading()
    {
        // Put a yml file in the drush folder.
        $drush_config_file = Path::join($this->webrootSlashDrush(), 'drush.yml');
        $a_drush_config_file = Path::join($this->webrootSlashDrush(), 'a.drush.yml');
        $b_drush_config_file = Path::join($this->webrootSlashDrush(), 'b.drush.yml');
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
        $drush_conf_as_string = print_r($output['drush-conf'], true);
        $this->assertContains($a_drush_config_file, $output['drush-conf'], "Loaded drush config files are: " . $drush_conf_as_string);
        $this->assertContains($b_drush_config_file, $output['drush-conf'], "Loaded drush config files are: " . $drush_conf_as_string);
        $this->assertEquals($test_uri, $output['uri']);
    }
}
