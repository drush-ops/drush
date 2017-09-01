<?php
namespace Drush\SiteAlias;

use PHPUnit\Framework\TestCase;

class SiteSpecParserTest extends TestCase
{
    use \Drush\FixtureFactory;

    /**
     * @dataProvider parserTestValues
     */
    public function testSiteSpecParser(
        $spec,
        $expected)
    {
        $root = $this->siteDir();
        $fixtureSite = '/' . basename($root);
        $parser = new SiteSpecParser();

        // If the test spec begins with '/fixtures', substitute the
        // actual path to our fixture site.
        $spec = preg_replace('%^/fixtures%', $root, $spec);

        // Parse it!
        $result = $parser->parse($spec, $root);

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

        $result = $parser->validSiteSpec($spec);
        $this->assertEquals($expected, $result);
    }

    public static function validSiteSpecs()
    {
        return [
            [ '/path/to/drupal#uri' ],
            [ 'user@server/path/to/drupal#uri' ],
            [ 'user@server/path/to/drupal' ],
            [ 'user@server#uri' ],
            [ '#uri' ],
        ];
    }

    public static function invalidSiteSpecs()
    {
        return [
            [ 'uri' ],
            [ '@/#' ],
            [ 'user@#uri' ],
            [ '@server/path/to/drupal#uri' ],
            [ 'user@server/path/to/drupal#' ],
            [ 'user@server/path/to/drupal#uri!' ],
            [ 'user@server/path/to/drupal##uri' ],
            [ 'user#server/path/to/drupal#uri' ],
       ];
    }

    public static function parserTestValues()
    {
        return [
            [
                'user@server/path#somemultisite',
                [
                    'user' => 'user',
                    'host' => 'server',
                    'root' => '/path',
                    'uri' => 'somemultisite',
                ],
            ],

            [
                'user@server/path',
                [
                    'user' => 'user',
                    'host' => 'server',
                    'root' => '/path',
                    'uri' => 'default',
                ],
            ],

            [
                '/fixtures#mymultisite',
                [
                    'root' => '/fixtures',
                    'uri' => 'mymultisite',
                ],
            ],

            [
                '#mymultisite',
                [
                    'root' => '/fixtures',
                    'uri' => 'mymultisite',
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
