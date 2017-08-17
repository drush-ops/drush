<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;

class SiteAliasNameTest extends TestCase
{
    public function testSiteAliasName()
    {
        // Test a non-ambiguous simple sitename alias.
        $name = new SiteAliasName('@simple');
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue(!$name->hasEnv());
        $this->assertTrue(!$name->isAmbiguous());
        $this->assertEquals('simple', $name->sitename());
        $this->assertEquals('@simple', (string)$name);

        // Add in a group and an env
        $name->setGroup('group');
        $name->setEnv('dev');
        $this->assertEquals('@group.simple.dev', (string)$name);

        // Test a non-ambiguous group.sitename.env alias.
        $name = new SiteAliasName('@group.site.env');
        $this->assertTrue($name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue(!$name->isAmbiguous());
        $this->assertEquals('group', $name->group());
        $this->assertEquals('site', $name->sitename());
        $this->assertEquals('env', $name->env());
        $this->assertEquals('@group.site.env', (string)$name);

        // Test an ambiguous one.two alias.
        $name = new SiteAliasName('@one.two');
        // By default, ambiguous names are assumed to be a sitename.env
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue($name->isAmbiguous());
        $this->assertEquals('one', $name->sitename());
        $this->assertEquals('two', $name->env());
        $this->assertEquals('@one.two', (string)$name);
        // Then we will assume it is a group.sitename
        $name->assumeAmbiguousIsGroup();
        $this->assertTrue($name->hasGroup());
        $this->assertTrue(!$name->hasEnv());
        $this->assertTrue($name->isAmbiguous());
        $this->assertEquals('one', $name->group());
        $this->assertEquals('two', $name->sitename());
        $this->assertEquals('@one.two', (string)$name);
        // Switch it back to a sitename.
        $name->assumeAmbiguousIsSitename();
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue($name->isAmbiguous());
        $this->assertEquals('one', $name->sitename());
        $this->assertEquals('two', $name->env());
        $this->assertEquals('@one.two', (string)$name);
        // Finally, we will 'disambiguate' is and confirm that
        // we can no longer make contrary assumptions.
        $name->disambiguate();
        $name->assumeAmbiguousIsGroup();
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue(!$name->isAmbiguous());
        $this->assertEquals('one', $name->sitename());
        $this->assertEquals('two', $name->env());
        $this->assertEquals('@one.two', (string)$name);
    }
}
