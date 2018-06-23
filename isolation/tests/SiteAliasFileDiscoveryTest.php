<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;
use Consolidation\SiteAlias\SiteAliasFileDiscovery;

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
        $this->assertBasename('single.site.yml', $path);
    }

    public function testSearchForMissingSingleAliasFile()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $path = $this->sut->findSingleSiteAliasFile('missing');
        $this->assertFalse($path);
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
