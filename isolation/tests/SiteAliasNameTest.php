<?php
namespace Drush\SiteAlias;

class SiteAliasNameTest extends \PHPUnit_Framework_TestCase
{
    public function testSiteAliasName()
    {
        // Test a non-ambiguous simple sitename alias.
        $name = new SiteAliasName('@simple');
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue(!$name->hasEnv());
        $this->assertTrue(!$name->isAmbiguous());
        $this->assertEquals('simple', $name->sitename());

        // Test a non-ambiguous group.sitename.env alias.
        $name = new SiteAliasName('@group.site.env');
        $this->assertTrue($name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue(!$name->isAmbiguous());
        $this->assertEquals('group', $name->group());
        $this->assertEquals('site', $name->sitename());
        $this->assertEquals('env', $name->env());

        // Test an ambiguous one.two alias.
        $name = new SiteAliasName('@one.two');
        // By default, ambiguous names are assumed to be a sitename.env
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue($name->isAmbiguous());
        $this->assertEquals('one', $name->sitename());
        $this->assertEquals('two', $name->env());
        // Then we will assume it is a group.sitename
        $name->assumeAmbiguousIsGroup();
        $this->assertTrue($name->hasGroup());
        $this->assertTrue(!$name->hasEnv());
        $this->assertTrue($name->isAmbiguous());
        $this->assertEquals('one', $name->group());
        $this->assertEquals('two', $name->sitename());
        // Switch it back to a sitename.
        $name->assumeAmbiguousIsSitename();
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue($name->isAmbiguous());
        $this->assertEquals('one', $name->sitename());
        $this->assertEquals('two', $name->env());
        // Finally, we will 'disambiguate' is and confirm that
        // we can no longer make contrary assumptions.
        $name->disambiguate();
        $name->assumeAmbiguousIsGroup();
        $this->assertTrue(!$name->hasGroup());
        $this->assertTrue($name->hasEnv());
        $this->assertTrue(!$name->isAmbiguous());
        $this->assertEquals('one', $name->sitename());
        $this->assertEquals('two', $name->env());
    }
}
