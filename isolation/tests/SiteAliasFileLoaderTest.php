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
        $name = new SiteAliasName('@simple');
        $result = $this->callProtected('loadSingleAliasFile', [$name]);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/simple', $result->get('root'));

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

    public function testLoadLegacy()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/legacy');
    }

    public function testLoad()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        // Look for a simple alias with no environments defined
        $name = new SiteAliasName('@simple');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/simple', $result->get('root'));

        // Look for a single alias without an environment specified.
        $name = new SiteAliasName('@single');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Same test, but with environment explicitly requested.
        $name = new SiteAliasName('@single.alternate');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/alternate/path/to/single', $result->get('root'));
        $this->assertEquals('bar', $result->get('foo'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('@missing');
        $result = $this->sut->load($name);
        $this->assertFalse($result);

        // Look for a group alias with environment explicitly provided.
        // Confirm that site alias inherits the common value for 'options.food'.
        $name = new SiteAliasName('@pets.dogs.dev');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/dogs', $result->get('root'));
        $this->assertEquals('meat', $result->get('options.food'));

        // Look for a group alias with environment explicitly provided.
        // Confirm that site alias has the overridden value for 'options.food'.
        $name = new SiteAliasName('@pets.birds.dev');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/birds', $result->get('root'));
        $this->assertEquals('seed', $result->get('options.food'));

        // Ask for sitename only; find result in an aliases.yml file.
        $name = new SiteAliasName('@trains');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/trains', $result->get('root'));

        // Ask for sitename only; find result in a group.aliases.yml file.
        $name = new SiteAliasName('@cats');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/cats', $result->get('root'));

        // Test fetching with a group and sitename without an environment specified.
        $name = new SiteAliasName('@pets.cats');
        $result = $this->sut->load($name);
        $this->assertTrue($result instanceof AliasRecord);
        $this->assertEquals('/path/to/cats', $result->get('root'));

        // Try to fetch an alias that does not exist.
        $name = new SiteAliasName('@missing');
        $result = $this->sut->load($name);
        $this->assertFalse($result);
    }

    public function testLoadAll()
    {
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/single');
        $this->sut->addSearchLocation($this->fixturesDir() . '/sitealiases/group');

        $all = $this->sut->loadAll();
        $this->assertEquals('@bathtub.dev,@drill.dev,@pets.birds.dev,@pets.cats.dev,@pets.dogs.dev,@single.alternate,@single.common,@single.dev,@transportation.cars.dev,@transportation.planes.dev,@transportation.trains.dev,@tuna.dev', implode(',', array_keys($all)));
    }
}
