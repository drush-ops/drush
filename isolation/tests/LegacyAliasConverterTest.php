<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;
use Consolidation\SiteAlias\SiteAliasFileDiscovery;

class LegacyAliasConverterTest extends TestCase
{
    use \Drush\FixtureFactory;
    use \Drush\FunctionUtils;
    use \Drush\FSUtils;

    protected $discovery;
    protected $target;

    protected function setUp()
    {
        $this->discovery = new SiteAliasFileDiscovery();
        $this->discovery->addSearchLocation($this->fixturesDir() . '/sitealiases/legacy');

        $this->sut = new LegacyAliasConverter($this->discovery);

        $this->target = $this->tempdir();
        $this->sut->setTargetDir($this->target);
    }

    protected function tearDown()
    {
        $this->removeDir($this->target);
    }

    protected function tempdir()
    {
        $tempfile = tempnam(sys_get_temp_dir(),'');
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (is_dir($tempfile)) {
            return $tempfile;
        }
    }

    public function testWriteOne()
    {
        $testPath = $this->target . '/testWriteOne.yml';
        $checksumPath = $this->target . '/.checksums/testWriteOne.md5';
        $testContents = 'test: This is the initial file contents';

        // Write the data once, and confirm it was written.
        $this->callProtected('writeOne', [$testPath, $testContents]);
        $this->assertStringEqualsFile($testPath, $testContents);

        // Check to see that the checksum file was written, and that
        // it contains a useful comment.
        $checksumContents = file_get_contents($checksumPath);
        $this->assertContains("# Checksum for converted Drush alias file testWriteOne.yml.\n# Delete this checksum file or modify testWriteOne.yml to prevent further updates to it.", $checksumContents);

        $overwriteContents = 'test: Overwrite the file contents';

        // Write the data again, and confirm it was changed.
        $this->callProtected('writeOne', [$testPath, $overwriteContents]);
        $this->assertStringEqualsFile($testPath, $overwriteContents);

        $simulatedEditedContents = 'test: My simulated edit';
        file_put_contents($testPath, $simulatedEditedContents);

        $ignoredContents = 'test: Data that is not written';

        // Write the yet data again; this time, confirm that
        // nothing changed, because the checksum does not match.
        $this->callProtected('writeOne', [$testPath, $ignoredContents]);
        $this->assertStringEqualsFile($testPath, $simulatedEditedContents);

        // Write yet again, this time removing the target so that it will
        // be writable again.
        unlink($testPath);
        $this->callProtected('writeOne', [$testPath, $overwriteContents]);
        $this->assertStringEqualsFile($testPath, $overwriteContents);
        $this->assertFileExists($checksumPath);

        // Remove the checksum file, and confirm that the target cannot
        // be overwritten
        unlink($checksumPath);
        $this->callProtected('writeOne', [$testPath, $ignoredContents]);
        $this->assertStringEqualsFile($testPath, $overwriteContents);
    }

    public function testConvertAll()
    {
        $legacyFiles = $this->discovery->findAllLegacyAliasFiles();
        $result = $this->callProtected('convertAll', [$legacyFiles]);
        ksort($result);
        $this->assertEquals('cc.site.yml,isp.site.yml,live.site.yml,nitrogen.site.yml,one.site.yml,outlandish-josh.site.yml,pantheon.site.yml,server.site.yml,update.site.yml', implode(',', array_keys($result)));
        //$this->assertEquals('', var_export($result, true));
        $this->assertEquals('dev-outlandish-josh.pantheonsite.io', $result['outlandish-josh.site.yml']['dev']['uri']);
    }

    public function testWriteAll()
    {
        $convertedFileFixtures = [
            'a.yml' => [
                'foo' => 'bar',
            ],
            'b.yml' => [
            ],
        ];

        $this->callProtected('cacheConvertedFilePath', ['b.aliases.drushrc.php', 'b.yml']);
        $this->callProtected('writeAll', [$convertedFileFixtures]);
        $this->assertFileExists($this->target . '/a.yml');
        $this->assertFileExists($this->target . '/.checksums/a.md5');
        $this->assertFileExists($this->target . '/b.yml');
        $this->assertFileExists($this->target . '/.checksums/b.md5');

        $this->assertStringEqualsFile($this->target . '/b.yml', "# This is a placeholder file used to track when b.aliases.drushrc.php was converted.\n# If you delete b.aliases.drushrc.php, then you may delete this file.");
        $aContents = file_get_contents($this->target . '/a.yml');
        $this->assertEquals('foo: bar', trim($aContents));
    }

    /**
     * Test to see if the data converter produces the right data for the
     * legacy alias file fixtures.
     *
     * @dataProvider convertLegacyFileTestData
     */
    public function testConvertLegacyFile($source, $expected)
    {
        $legacyFile = $this->fixturesDir() . '/sitealiases/legacy/' . $source;
        $result = $this->callProtected('convertLegacyFile', [$legacyFile]);
        $this->assertEquals($expected, $result);
    }

    public function convertLegacyFileTestData()
    {
        return [
            [
                'one.alias.drushrc.php',
                [
                    'one.site.yml' =>
                    [
                        'dev' =>
                        [
                            'uri' => 'http://example.com',
                            'root' => '/path/to/drupal',
                        ],
                    ],
                ],
            ],

            [
                'server.aliases.drushrc.php',
                [
                    'isp.site.yml' =>
                    [
                        'dev' =>
                        [
                            'host' => 'hydrogen.server.org',
                            'user' => 'www-admin',
                        ],
                    ],
                    'nitrogen.site.yml' =>
                    [
                        'dev' =>
                        [
                            'host' => 'nitrogen.server.org',
                            'user' => 'admin',
                        ],
                    ],
                ],
            ],

            [
                'pantheon.aliases.drushrc.php',
                [
                    'outlandish-josh.site.yml' =>
                    [
                        'dev' =>
                        [
                            'uri' => 'dev-outlandish-josh.pantheonsite.io',
                            'host' => 'appserver.dev.site-id.drush.in',
                            'user' => 'dev.site-id',
                            'paths' => [
                                'files' => 'code/sites/default/files',
                                'drush-script' => 'drush',
                            ],
                            'options' => [
                                'db-url' => 'mysql://pantheon:pw@dbserver.dev.site-id.drush.in:21086/pantheon',
                                'db-allows-remote' => true,
                            ],
                            'ssh' => [
                                'options' => '-p 2222 -o "AddressFamily inet"',
                            ],
                        ],
                        'live' =>
                        [
                            'uri' => 'www.outlandishjosh.com',
                            'host' => 'appserver.live.site-id.drush.in',
                            'user' => 'live.site-id',
                            'paths' => [
                                'files' => 'code/sites/default/files',
                                'drush-script' => 'drush',
                            ],
                            'options' => [
                                'db-url' => 'mysql://pantheon:pw@dbserver.live.site-id.drush.in:10516/pantheon',
                                'db-allows-remote' => true,
                            ],
                            'ssh' => [
                                'options' => '-p 2222 -o "AddressFamily inet"',
                            ],
                        ],
                        'test' =>
                        [
                            'uri' => 'test-outlandish-josh.pantheonsite.io',
                            'host' => 'appserver.test.site-id.drush.in',
                            'user' => 'test.site-id',
                            'paths' => [
                                'files' => 'code/sites/default/files',
                                'drush-script' => 'drush',
                            ],
                            'options' => [
                                'db-url' => 'mysql://pantheon:pw@dbserver.test.site-id.drush.in:11621/pantheon',
                                'db-allows-remote' => true,
                            ],
                            'ssh' => [
                                'options' => '-p 2222 -o "AddressFamily inet"',
                            ],
                        ],
                    ],
                ],
            ],

/*
            // Future: this test includes 'parent' and 'target-command-specific',
            // which are not converted yet.

            [
                'cc.aliases.drushrc.php',
                [
                    'cc.site.yml' =>
                    [
                        'live' =>
                        [
                        ],

                        'update' =>
                        [
                        ],
                    ],
                ],
            ],
*/
        ];
    }
}
