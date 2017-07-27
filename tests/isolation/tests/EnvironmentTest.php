<?php
namespace Drush\Config;

/**
 * The code tested here is pretty trivial; this test suite also serves
 * the dual purpose of testing that the fixture data is reasonable.
 */
class EnvironmentTest extends \PHPUnit_Framework_TestCase
{
    protected $fixtures;
    protected $environment;
    protected $home;
    protected $fixturesDir;

    function setup()
    {
        $this->fixtures = new \Drush\FixtureFactory();
    }

    function testExportConfigData()
    {
        $data = $this->fixtures->environment()->exportConfigData();
        $this->assertEquals($this->fixtures->homeDir(), $data['env']['cwd']);
    }

    function testDocsPath()
    {
        $docsPath = $this->fixtures->environment()->docsPath();
        $this->assertTrue(is_string($docsPath), 'A docsPath was found');
        $this->assertTrue(file_exists("$docsPath/README.md"), 'README.md exists at docsPath');
    }

    function testDrushConfigFileFixturesExist()
    {
        $fixturesDir = $this->fixtures->fixturesDir();
        $this->assertTrue(file_exists("$fixturesDir/etc/drush/drush.yml"), '/etc/drush/drush.yml exists');
        $this->assertTrue(file_exists("$fixturesDir/home/.drush/drush.yml"), '/home/.drush/drush.yml exists');
    }
}
