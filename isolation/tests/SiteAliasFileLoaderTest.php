<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;

class SiteAliasFileLoaderTest extends TestCase
{
    use \Drush\FixtureFactory;
    use \Drush\FunctionUtils;

    function setUp()
    {
        $this->sut = new SiteAliasFileLoader();
    }

    public function testLoadSingleAliasFile()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        // Look for a simple alias with no environments defined
        $name = new SiteAliasName('simple');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/simple', $result->get('root'));

        // Look for a single alias without an environment specified.
        $name = new SiteAliasName('single');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Same test, but with environment explicitly requested.
        $name = new SiteAliasName('single', 'alternate');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/alternate/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('missing');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertFalse($result);
    }

    public function testLoadLegacy()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/legacy');
    }

    public function testLoad()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        // Look for a simple alias with no environments defined
        $name = new SiteAliasName('simple');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/simple', $result->get('root'));

        // Look for a single alias without an environment specified.
        $name = new SiteAliasName('single');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Same test, but with environment explicitly requested.
        $name = new SiteAliasName('single', 'alternate');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/alternate/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('missing');
        $result = $this->sut->load($name);
        $this->assertFalse($result);

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('missing');
        $result = $this->sut->load($name);
        $this->assertFalse($result);
    }

    public function testLoadAll()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');

        $all = $this->sut->loadAll();
        $this->assertEquals('@single.alternate,@single.common,@single.dev', implode(',', array_keys($all)));
    }
}
