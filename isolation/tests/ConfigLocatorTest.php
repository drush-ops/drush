<?php
namespace Drush\Config;

use PHPUnit\Framework\TestCase;

/**
 * Test the config loader. Also exercises the EnvironmentConfigLoader.
 */
class ConfigLocatorTest extends TestCase
{
    use \Drush\FixtureFactory;

    /**
     * Test a config locator initialized only with data from the fixture's environment
     */
    function testOnlyEnvironmentData()
    {
        $configLocator = new ConfigLocator('TEST_');
        $configLocator->addEnvironment($this->environment());
        $config = $configLocator->config();
        $this->assertEquals($this->homeDir(), $config->get('env.cwd'));
    }

    /**
     * Test a comprehensive load of all default fixture data.
     */
    function testLoadAll()
    {
        $configLocator = $this->createConfigLocator();

        $sources = $configLocator->sources();
        //$this->assertEquals('environment', $sources['env']['cwd']);
        $this->assertEquals($this->fixturesDir() . '/etc/drush/drush.yml', $sources['test']['system']);
        $this->assertEquals($this->fixturesDir() . '/home/.drush/drush.yml', $sources['test']['home']);
        $this->assertEquals($this->fixturesDir() . '/sites/d8/drush/drush.yml', $sources['test']['site']);
        $this->assertEquals($this->environment()->drushBasePath() . '/drush.yml', $sources['drush']['php']['minimum-version']);

        $config = $configLocator->config();

        $this->assertEquals($this->homeDir(), $config->get('env.cwd'));
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
        $configLocator = $this->createConfigLocator(true);

        /*
        $sources = $configLocator->sources();
        //$this->assertEquals('environment', $sources['env']['cwd']);
        $this->assertTrue(!isset($sources['test']['system']));
        $this->assertTrue(!isset($sources['test']['home']));
        $this->assertEquals($this->siteDir() . '/drush/drush.yml', $sources['test']['site']);
        */

        $config = $configLocator->config();
        $this->assertEquals($this->homeDir(), $config->get('env.cwd'));
        $this->assertTrue(!$config->has('test.system'));
        $this->assertTrue(!$config->has('test.home'));
        $this->assertEquals('A site-specific setting', $config->get('test.site'));
    }

    function testAliasPaths()
    {
        $configLocator = $this->createConfigLocator();
        $aliasPaths = $configLocator->getSiteAliasPaths(['/home/user/aliases'], $this->environment());
        $aliasPaths = array_map(
            function ($item) {
                return str_replace(dirname(__DIR__) . '/', '', $item);
            },
            $aliasPaths
        );
        sort($aliasPaths);

        $expected = '/home/user/aliases,fixtures/sites/d8/drush/sites';
        $this->assertEquals($expected, implode(',', $aliasPaths));
    }

    /**
     * Create a config locator from All The Sources, for use in multiple tests.
     */
    protected function createConfigLocator($isLocal = false, $configPath = '')
    {
        $configLocator = new ConfigLocator('TEST_');
        $configLocator->collectSources();
        $configLocator->setLocal($isLocal);
        $configLocator->addUserConfig([$configPath], $this->environment()->systemConfigPath(), $this->environment()->userConfigPath());
        $configLocator->addDrushConfig($this->environment()->drushBasePath());

        // Make our environment settings available as configuration items
        $configLocator->addEnvironment($this->environment());

        $configLocator->addSitewideConfig($this->siteDir());

        return $configLocator;
    }
}
