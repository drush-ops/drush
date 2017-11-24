<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;

class SiteAliasNameTest extends TestCase
{
    public function testSiteAliasName()
    {
        // Test an ambiguous sitename or env alias.
        $name = SiteAliasName::parse('@simple');
        $this->assertTrue(!$name->hasSitename());
        $this->assertTrue($name->hasEnv());
        $this->assertEquals('simple', $name->env());
        $this->assertEquals('@self.simple', (string)$name);

        // Test a non-ambiguous sitename.env alias.
        $name = SiteAliasName::parse('@site.env');
        $this->assertTrue($name->hasSitename());
        $this->assertTrue($name->hasEnv());
        $this->assertEquals('site', $name->sitename());
        $this->assertEquals('env', $name->env());
        $this->assertEquals('@site.env', (string)$name);

        // Test an invalid alias
        $name = SiteAliasName::parse('!site.env');
        $this->assertFalse($name->hasSitename());
        $this->assertFalse($name->hasEnv());
    }
}
