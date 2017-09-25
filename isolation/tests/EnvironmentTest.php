<?php
namespace Drush\Config;

use PHPUnit\Framework\TestCase;

/**
 * The code tested here is pretty trivial; this test suite also serves
 * the dual purpose of testing that the fixture data is reasonable.
 */
class EnvironmentTest extends TestCase
{
    use \Drush\FixtureFactory;

    function testExportConfigData()
    {
        $data = $this->environment()->exportConfigData();
        $this->assertEquals($this->homeDir(), $data['env']['cwd']);
    }

    function testDocsPath()
    {
        $docsPath = $this->environment()->docsPath();
        $this->assertTrue(is_string($docsPath), 'A docsPath was found');
        $this->assertTrue(file_exists("$docsPath/README.md"), 'README.md exists at docsPath');
    }

    function testDrushConfigFileFixturesExist()
    {
        $fixturesDir = $this->fixturesDir();
        $this->assertTrue(file_exists("$fixturesDir/etc/drush/drush.yml"), '/etc/drush/drush.yml exists');
        $this->assertTrue(file_exists("$fixturesDir/home/.drush/drush.yml"), '/home/.drush/drush.yml exists');
    }
}
