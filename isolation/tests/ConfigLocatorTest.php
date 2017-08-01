<?php
namespace Drush\Config;

/**
 * Test the config loader. Also exercises the EnvironmentConfigLoader.
 */
class ConfigLocatorTest extends \PHPUnit_Framework_TestCase
{
    protected $fixtures;

    function setup()
    {
        $this->fixtures = new \Drush\FixtureFactory();
    }

    /**
     * Test a config locator initialized only with data from the fixture's environment
     */
    function testOnlyEnvironmentData()
    {
        $configLocator = new ConfigLocator();
        $configLocator->addEnvironment($this->fixtures->environment());
        $config = $configLocator->config();
        $this->assertEquals($this->fixtures->homeDir(), $config->get('env.cwd'));
    }

    /**
     * Test a comprehensive load of all default fixture data.
     */
    function testLoadAll()
    {
        $configLocator = $this->createConfigLoader();

        $sources = $configLocator->sources();
        //$this->assertEquals('environment', $sources['env']['cwd']);
        $this->assertEquals($this->fixtures->fixturesDir() . '/etc/drush/drush.yml', $sources['test']['system']);
        $this->assertEquals($this->fixtures->fixturesDir() . '/home/.drush/drush.yml', $sources['test']['home']);
        $this->assertEquals($this->fixtures->fixturesDir() . '/sites/d8/drush/drush.yml', $sources['test']['site']);
        $this->assertEquals($this->fixtures->environment()->drushBasePath() . '/drush.yml', $sources['drush']['php']['minimum-version']);

        $config = $configLocator->config();

        $this->assertEquals($this->fixtures->homeDir(), $config->get('env.cwd'));
        $this->assertEquals('A system-wide setting', $config->get('test.system'));
        $this->assertEquals('A user-specific setting', $config->get('test.home'));
        $this->assertEquals('A site-specific setting', $config->get('test.site'));
        $this->assertTrue($config->has('drush.php.minimum-version'));
    }

    /**
     * Test loading default fixture data in 'local' mode. This prevents Drush
     * from loading any configuration file in any "global" location. In this
     * context, "global" means anything that is not site-local, including the
     * configuration file in the user's home directory, etc.
     */
    function testLocalMode()
    {
        $configLocator = $this->createConfigLoader(true);

        /*
        $sources = $configLocator->sources();
        //$this->assertEquals('environment', $sources['env']['cwd']);
        $this->assertTrue(!isset($sources['test']['system']));
        $this->assertTrue(!isset($sources['test']['home']));
        $this->assertEquals($this->fixtures->siteDir() . '/drush/drush.yml', $sources['test']['site']);
        */

        $config = $configLocator->config();
        $this->assertEquals($this->fixtures->homeDir(), $config->get('env.cwd'));
        $this->assertTrue(!$config->has('test.system'));
        $this->assertTrue(!$config->has('test.home'));
        $this->assertEquals('A site-specific setting', $config->get('test.site'));
    }

    /**
     * Create a config locator from All The Sources, for use in multiple tests.
     */
    protected function createConfigLoader($isLocal = false, $configPath = '', $aliasPath = '', $alias = '')
    {
        $configLocator = new ConfigLocator();
        $configLocator->collectSources();
        $configLocator->setLocal($isLocal);
        $configLocator->addUserConfig($configPath, $this->fixtures->environment()->systemConfigPath(), $this->fixtures->environment()->userConfigPath());
        $configLocator->addDrushConfig($this->fixtures->environment()->drushBasePath());

        // Make our environment settings available as configuration items
        $configLocator->addEnvironment($this->fixtures->environment());

        $configLocator->addSitewideConfig($this->fixtures->siteDir());

        return $configLocator;
    }
}
