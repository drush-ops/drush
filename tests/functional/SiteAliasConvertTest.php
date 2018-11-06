<?php

namespace Unish;

use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * @group base
 */
class SiteAliasConvertTest extends CommandUnishTestCase
{

  /**
   * Test functionality of site:alias-convert.
   */
    public function testSiteAliasConvert()
    {
        // Use a custom destination so we don't have to install a Drupal.
        $destination = Path::join(self::getSandbox(), 'testSiteAliasConvert');
        $this->drush('site:alias-convert', ['destination' => $destination], ['sources' =>  Path::join(__DIR__, '/resources/alias-fixtures')]);

        // Write config alias-path that specifies our destination.
        $config['drush']['paths']['alias-path'][] = $destination;
        file_put_contents(Path::join(self::getSandbox(), 'etc/drush/drush.yml'), Yaml::dump($config, 3));

        $this->drush('site:alias', [], ['format' => 'json']);
        // $this->assertEquals('', $this->getOutput());
        $json = $this->getOutputFromJSON();
        $this->assertObjectHasAttribute('@testSiteAliasConvert.drupalvm.dev', $json);
        $this->assertObjectHasAttribute('@testSiteAliasConvert.www-drupalvm.dev', $json);
        $dev = $json->{'@testSiteAliasConvert.drupalvm.dev'};
        $this->assertSame('drupalvm.dev', $dev->host);
        $this->assertSame('-o PasswordAuthentication=no -i /.vagrant.d/insecure_private_key', $dev->ssh->{'options'});
        $this->assertSame('/var/www/drupalvm/drupal/vendor/drush/drush/drush', $dev->paths->{'drush-script'});
    }
}
