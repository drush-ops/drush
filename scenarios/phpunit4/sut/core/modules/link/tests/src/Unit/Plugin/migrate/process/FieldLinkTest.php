<?php

namespace Drupal\Tests\link\Unit\Plugin\migrate\process;

use Drupal\link\Plugin\migrate\process\FieldLink;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;

/**
 * @group Link
 */
class FieldLinkTest extends UnitTestCase {

  /**
   * Test the url transformations in the FieldLink process plugin.
   *
   * @dataProvider canonicalizeUriDataProvider
   */
  public function testCanonicalizeUri($url, $expected, $configuration = []) {
    $link_plugin = new FieldLink($configuration, '', [], $this->getMock(MigrationInterface::class));
    $transformed = $link_plugin->transform([
      'url' => $url,
      'title' => '',
      'attributes' => serialize([]),
    ], $this->getMock(MigrateExecutableInterface::class), $this->getMockBuilder(Row::class)->disableOriginalConstructor()->getMock(), NULL);
    $this->assertEquals($expected, $transformed['uri']);
  }

  /**
   * Data provider for testCanonicalizeUri.
   */
  public function canonicalizeUriDataProvider() {
    return [
      'Simple front-page' => [
        '<front>',
        'internal:/',
      ],
      'Front page with query' => [
        '<front>?query=1',
        'internal:/?query=1',
      ],
      'No leading forward slash' => [
        'node/10',
        'internal:/node/10',
      ],
      'Leading forward slash' => [
        '/node/10',
        'internal:/node/10',
      ],
      'Existing scheme' => [
        'scheme:test',
        'scheme:test',
      ],
      'Absolute URL with protocol prefix' => [
        'http://www.google.com',
        'http://www.google.com',
      ],
      'Absolute URL without protocol prefix' => [
        'www.yahoo.com',
        'http://www.yahoo.com',
      ],
      'Absolute URL without protocol prefix nor www' => [
        'yahoo.com',
        'https://yahoo.com',
        ['uri_scheme' => 'https://'],
      ],
      'Absolute URL with non-standard characters' => [
        'http://www.ßÀÑÐ¥ƒå¢ë.com',
        'http://www.ßÀÑÐ¥ƒå¢ë.com',
      ],
      'Absolute URL with non-standard characters, without protocol prefix' => [
        'www.ÐØÑ¢åþë.com',
        'http://www.ÐØÑ¢åþë.com',
      ],
      'Absolute URL with non-standard top level domain' => [
        'http://www.example.xxx',
        'http://www.example.xxx',
      ],
      'Internal link with fragment' => [
        '/node/10#top',
        'internal:/node/10#top',
      ],
      'External link with fragment' => [
        'http://www.example.com/page#links',
        'http://www.example.com/page#links',
      ],
    ];
  }

}
