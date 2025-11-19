<?php

declare(strict_types=1);

namespace Drush\SiteAlias;

use Consolidation\SiteAlias\SiteAliasFileDiscovery;
use PHPUnit\Framework\TestCase;
use Unish\Utils\Fixtures;

final class SiteAliasFileDiscoveryTest extends TestCase
{
    use Fixtures;

    /**
     * @var SiteAliasFileDiscovery|mixed
     */
    public $sut;

    public function setup(): void
    {
        $this->sut = new SiteAliasFileDiscovery();
    }

    public function testSearchForSingleAliasFile(): void
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $path = $this->sut->findSingleSiteAliasFile('single');
        $this->assertLocation('single', $path);
        $this->assertBasename('single.site.yml', $path);
    }

    public function testSearchForMissingSingleAliasFile(): void
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
            fn($item) => basename(dirname($item)) . '/' . basename($item),
            $result
        );

        sort($result);

        return $result;
    }
}
