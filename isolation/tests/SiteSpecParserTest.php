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
        $root = dirname(__DIR__) . '/fixtures/sites/d8';
        $parser = new SiteSpecParser($root);

        $result = $parser->parse($spec);
        if (isset($result['root'])) {
            $result['root'] = preg_replace('%.*/fixtures/%', '/fixtures/', $result['root']);
        }
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
                '/path#somemultisite',
                [
                    'remote-user' => '',
                    'remote-server' => '',
                    'root' => '/path',
                    'sitename' => 'somemultisite',
                ],
            ],

            [
                '#somemultisite',
                [
                ],
            ],

            [
                '#mymultisite',
                [
                    'remote-user' => '',
                    'remote-server' => '',
                    'root' => '/fixtures/sites/d8',
                    'sitename' => 'mymultisite',
                ],
            ],

        ];
    }
}
