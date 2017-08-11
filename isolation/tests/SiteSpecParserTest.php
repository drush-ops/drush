<?php
namespace Drush\SiteAlias;

class SiteSpecParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider parserTestValues
     */
    public function testSiteSpecParser(
        $spec,
        $expected)
    {
        $fixtureSite = '/fixtures/sites/d8';
        $root = dirname(__DIR__) . $fixtureSite;
        $parser = new SiteSpecParser($root);

        // If the test spec begins with '/fixtures', substitute the
        // actual path to our fixture site.
        $spec = preg_replace('%^/fixtures%', $root, $spec);

        // Parse it!
        $result = $parser->parse($spec);

        // If the result contains the path to our fixtures site, replace
        // it with the simple string '/fixtures'.
        if (isset($result['root'])) {
            $result['root'] = preg_replace("%.*$fixtureSite%", '/fixtures', $result['root']);
        }

        // Compare the altered result with the expected value.
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider validSiteSpecs
     */
    public function testValidSiteSpecs($spec)
    {
        $this->isSpecValid($spec, true);
    }

    /**
     * @dataProvider invalidSiteSpecs
     */
    public function testInvalidSiteSpecs($spec)
    {
        $this->isSpecValid($spec, false);
    }

    protected function isSpecValid($spec, $expected)
    {
        $parser = new SiteSpecParser();

        $result = $parser->valid($spec);
        $this->assertEquals($expected, $result);
    }

    public static function validSiteSpecs()
    {
        return [
            [ '/path/to/drupal#sitename' ],
            [ 'user@server/path/to/drupal#sitename' ],
            [ 'user@server/path/to/drupal' ],
            [ 'user@server#sitename' ],
            [ '#sitename' ],
        ];
    }

    public static function invalidSiteSpecs()
    {
        return [
            [ 'sitename' ],
            [ '@/#' ],
            [ 'user@#sitename' ],
            [ '@server/path/to/drupal#sitename' ],
            [ 'user@server/path/to/drupal#' ],
            [ 'user@server/path/to/drupal#sitename!' ],
            [ 'user@server/path/to/drupal##sitename' ],
            [ 'user#server/path/to/drupal#sitename' ],
       ];
    }

    public static function parserTestValues()
    {
        return [
            [
                'user@server/path#somemultisite',
                [
                    'remote-user' => 'user',
                    'remote-server' => 'server',
                    'root' => '/path',
                    'sitename' => 'somemultisite',
                ],
            ],

            [
                'user@server/path',
                [
                    'remote-user' => 'user',
                    'remote-server' => 'server',
                    'root' => '/path',
                    'sitename' => 'default',
                ],
            ],

            [
                '/fixtures#mymultisite',
                [
                    'remote-user' => '',
                    'remote-server' => '',
                    'root' => '/fixtures',
                    'sitename' => 'mymultisite',
                ],
            ],

            [
                '#mymultisite',
                [
                    'remote-user' => '',
                    'remote-server' => '',
                    'root' => '/fixtures',
                    'sitename' => 'mymultisite',
                ],
            ],

            [
                '/fixtures#somemultisite',
                [
                ],
            ],

            [
                '/path#somemultisite',
                [
                ],
            ],

            [
                '/path#mymultisite',
                [
                ],
            ],

            [
                '#somemultisite',
                [
                ],
            ],
        ];
    }
}
