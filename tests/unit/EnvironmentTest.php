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
        $this->assertInternalType('string', $docsPath, 'A docsPath was found');
        $this->assertFileExists("$docsPath/README.md", 'README.md exists at docsPath');
    }

    function testDrushConfigFileFixturesExist()
    {
        $fixturesDir = $this->fixturesDir();
        $this->assertFileExists("$fixturesDir/etc/drush/drush.yml", '/etc/drush/drush.yml exists');
        $this->assertFileExists("$fixturesDir/home/.drush/drush.yml", '/home/.drush/drush.yml exists');
    }
}
