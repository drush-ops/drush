<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;

class SiteAliasFileDiscoveryTest extends TestCase
{
    use \Drush\FixtureFactory;
    use \Drush\FunctionUtils;

    function setUp()
    {
        $this->sut = new SiteAliasFileDiscovery();
    }

    public function testSearchForSingleAliasFile()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $path = $this->sut->findSingleSiteAliasFile('single');
        $this->assertLocation('single', $path);
        $this->assertBasename('single.alias.yml', $path);
    }

    public function testSearchForMissingSingleAliasFile()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $path = $this->sut->findSingleSiteAliasFile('missing');
        $this->assertFalse($path);
    }

    public function testSearchForGroupAliasFile()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $path = $this->sut->findGroupAliasFile('pets');
        $this->assertLocation('group', $path);
        $this->assertBasename('pets.aliases.yml', $path);
    }

    public function testSearchForMissingGroupAliasFile()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $path = $this->sut->findGroupAliasFile('missing');
        $this->assertFalse($path);
    }

    public function testUnnamedGroupFileCache()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');
        $this->assertTrue(file_exists($this->fixturesDir() . '/sitealiases/group/aliases.yml'));

        $result = $this->callProtected('findUnnamedGroupAliasFiles');
        $result = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('group/aliases.yml', implode(',', $result));
    }

    public function testGroupFileCache()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $result = $this->callProtected('groupAliasFileCache');
        $paths = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('group/pets.aliases.yml,group/transportation.aliases.yml', implode(',', $paths));

        $this->assertTrue(array_key_exists('pets', $result));
        $this->assertLocation('group', $result['pets']);
        $this->assertBasename('pets.aliases.yml', $result['pets']);
    }

    public function testFindAllGroupAliasFiles()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $result = $this->sut->findAllGroupAliasFiles();
        $paths = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('group/aliases.yml,group/pets.aliases.yml,group/transportation.aliases.yml', implode(',', $paths));
    }

    public function testFindAllLegacyAliasFiles()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/legacy');

        $result = $this->sut->findAllLegacyAliasFiles();
        $paths = $this->simplifyToBasenamesWithLocation($result);
        $this->assertEquals('legacy/cc.aliases.drushrc.php,legacy/one.alias.drushrc.php,legacy/pantheon.aliases.drushrc.php,legacy/server.aliases.drushrc.php', implode(',', $paths));
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
}
