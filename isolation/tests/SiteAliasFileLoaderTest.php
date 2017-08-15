<?php
namespace Drush\SiteAlias;

class SiteAliasFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    use \Drush\FixtureFactory;
    protected $loader;

    function setup()
    {
        $this->loader = new SiteAliasFileLoader();
    }

    public function testLoadSingleAliasFile()
    {
        $siteAliasesDir = $this->fixturesDir() . '/sitealiases/single';
        $this->loader->addSearchLocation($siteAliasesDir);

        // Look for a single alias without an environment specified.
        $name = new SiteAliasName('@single');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Same test, but with environment explicitly requested.
        $name = new SiteAliasName('@single.alternate');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/alternate/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('@missing');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertFalse($result);
    }

    public function testLoad()
    {
        $this->loader->addSearchLocation($this->fixturesDir() . '/sitealiases/single');
        $this->loader->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        // Look for a single alias without an environment specified.
        $name = new SiteAliasName('@single');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Same test, but with environment explicitly requested.
        $name = new SiteAliasName('@single.alternate');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/alternate/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('@missing');
        $result = $this->loader->load($name);
        $this->assertFalse($result);

        // Look for a group alias with environment explicitly provided.
        $name = new SiteAliasName('@pets.dogs.default');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/dogs', $result->get('root'));
        $this->assertEquals('meat', $result->get('options.food'));

        // Look for a group alias with environment explicitly provided.
        $name = new SiteAliasName('@pets.birds.default');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/birds', $result->get('root'));
        $this->assertEquals('seed', $result->get('options.food'));

        // Ask for sitename only; find result in an aliases.yml file.
        $name = new SiteAliasName('@trains');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/trains', $result->get('root'));

        // Ask for sitename only; find result in a group.aliases.yml file.
        $name = new SiteAliasName('@cats');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/cats', $result->get('root'));

        // Test fetching with a group and sitename without an environment specified.
        $name = new SiteAliasName('@pets.cats');
        $result = $this->loader->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/cats', $result->get('root'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('@missing');
        $result = $this->loader->load($name);
        $this->assertFalse($result);
    }

    protected function callProtected($methodName, $args)
    {
        $r = new \ReflectionMethod(SiteAliasFileLoader::class, $methodName);
        $r->setAccessible(true);
        return $r->invokeArgs($this->loader, $args);
    }
}
