<?php
namespace Drush\SiteAlias;

class SiteAliasFileDiscoveryTest extends \PHPUnit_Framework_TestCase
{
    use \Drush\FixtureFactory;
    protected $discovery;

    function setup()
    {
        $this->discovery = new SiteAliasFileDiscovery();
    }

    public function testSearchForSingleAliasFile()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $path = $this->discovery->findSingleSiteAliasFile('single');
        $this->assertLocation('single', $path);
        $this->assertBasename('single.alias.yml', $path);
    }

    public function testSearchForMissingSingleAliasFile()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $path = $this->discovery->findSingleSiteAliasFile('missing');
        $this->assertFalse($path);
    }

    public function testSearchForGroupAliasFile()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $path = $this->discovery->findGroupAliasFile('pets');
        $this->assertLocation('group', $path);
        $this->assertBasename('pets.aliases.yml', $path);
    }

    public function testSearchForMissingGroupAliasFile()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $path = $this->discovery->findGroupAliasFile('missing');
        $this->assertFalse($path);
    }

    public function testUnnamedGroupFileCache()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/group');
        $this->assertTrue(file_exists($this->fixturesDir() . '/sitealiases/group/aliases.yml'));

        $result = $this->callProtected('findUnnamedGroupAliasFiles');
        $result = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('group/aliases.yml', implode(',', $result));
    }

    public function testGroupFileCache()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $result = $this->callProtected('groupAliasFileCache');
        $paths = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('group/pets.aliases.yml,group/transportation.aliases.yml', implode(',', $paths));

        $this->assertTrue(array_key_exists('pets', $result));
        $this->assertLocation('group', $result['pets']);
        $this->assertBasename('pets.aliases.yml', $result['pets']);
    }

    public function testFindAllGroupAliasFiles()
    {
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $result = $this->discovery->findAllGroupAliasFiles();
        $paths = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('group/aliases.yml,group/pets.aliases.yml,group/transportation.aliases.yml', implode(',', $paths));
    }

    protected function assertLocation($expected, $path)
    {
        $this->assertEquals($expected, basename(dirname($path)));
    }

    protected function assertBasename($expected, $path)
    {
        $this->assertEquals($expected, basename($path));
    }

    protected function simplifyToBasenamesWithLocation($result)
    {
        if (!is_array($result)) {
            return $result;
        }

        $result = array_map(
            function ($item) {
                return basename(dirname($item)) . '/' . basename($item);
            }
            ,
            $result
        );

        sort($result);

        return $result;
    }

    protected function callProtected($methodName, $args = [])
    {
        $r = new \ReflectionMethod(SiteAliasFileDiscovery::class, $methodName);
        $r->setAccessible(true);
        return $r->invokeArgs($this->discovery, $args);
    }
}
