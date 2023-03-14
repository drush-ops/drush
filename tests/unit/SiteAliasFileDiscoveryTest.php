<?php

declare(strict_types=1);

namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;
use Consolidation\SiteAlias\SiteAliasFileDiscovery;
use Unish\Utils\Fixtures;
use Unish\Utils\FunctionUtils;

class SiteAliasFileDiscoveryTest extends TestCase
{
    use Fixtures;
    use FunctionUtils;

    /**
     * @var SiteAliasFileDiscovery|mixed
     */
    public $sut;

    public function setup(): void
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
            },
            $result
        );

        sort($result);

        return $result;
    }
}
