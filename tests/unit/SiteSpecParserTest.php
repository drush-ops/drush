<?php

declare(strict_types=1);

namespace Drush\SiteAlias;

use PHPUnit\Framework\Attributes\DataProvider;
use Unish\Utils\Fixtures;
use PHPUnit\Framework\TestCase;

final class SiteSpecParserTest extends TestCase
{
    use Fixtures;

    #[DataProvider('parserTestValues')]
    public function testSiteSpecParser(
        string $spec,
        array $expected
    ): void {

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

    #[DataProvider('validSiteSpecs')]
    public function testValidSiteSpecs(string $spec): void
    {
        $this->isSpecValid($spec, true);
    }

    #[DataProvider('invalidSiteSpecs')]
    public function testInvalidSiteSpecs(string $spec): void
    {
        $this->isSpecValid($spec, false);
    }

    protected function isSpecValid($spec, $expected)
    {
        $parser = new SiteSpecParser();

        $result = $parser->validSiteSpec($spec);
        $this->assertEquals($expected, $result);
    }

    public static function validSiteSpecs(): \Iterator
    {
        yield [ '/path/to/drupal#uri' ];
        yield [ 'user@server/path/to/drupal#uri' ];
        yield [ 'user.name@example.com/path/to/drupal#uri' ];
        yield [ 'user@server/path/to/drupal' ];
        yield [ 'user@example.com/path/to/drupal' ];
        yield [ 'user@server#uri' ];
        yield [ 'user@example.com#uri' ];
        yield [ '#uri' ];
    }

    public static function invalidSiteSpecs(): \Iterator
    {
        yield [ 'uri' ];
        yield [ '@/#' ];
        yield [ 'user@#uri' ];
        yield [ '@server/path/to/drupal#uri' ];
        yield [ 'user@server/path/to/drupal#' ];
        yield [ 'user@server/path/to/drupal#uri!' ];
        yield [ 'user@server/path/to/drupal##uri' ];
        yield [ 'user#server/path/to/drupal#uri' ];
    }

    public static function parserTestValues(): \Iterator
    {
        yield [
            'user@server/path#somemultisite',
            [
                'user' => 'user',
                'host' => 'server',
                'root' => '/path',
                'uri' => 'somemultisite',
            ],
        ];
        yield [
            'user.name@example.com/path#somemultisite',
            [
                'user' => 'user.name',
                'host' => 'example.com',
                'root' => '/path',
                'uri' => 'somemultisite',
            ],
        ];
        yield [
            'user@server/path',
            [
                'user' => 'user',
                'host' => 'server',
                'root' => '/path',
                'uri' => 'default',
            ],
        ];
        yield [
            'user.name@example.com/path',
            [
                'user' => 'user.name',
                'host' => 'example.com',
                'root' => '/path',
                'uri' => 'default',
            ],
        ];
        yield [
            '/fixtures#mymultisite',
            [
                'root' => '/fixtures',
                'uri' => 'mymultisite',
            ],
        ];
        yield [
            '#mymultisite',
            [
                'root' => '/fixtures',
                'uri' => 'mymultisite',
            ],
        ];
        yield [
            '/fixtures#somemultisite',
            [
            ],
        ];
        yield [
            '/path#somemultisite',
            [
            ],
        ];
        yield [
            '/path#mymultisite',
            [
            ],
        ];
        yield [
            '#somemultisite',
            [
            ],
        ];
    }
}
