<?php

namespace Drush\Drupal\Migrate;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drush\Drupal\Migrate\MigrateUtils
 */
class MigrateRunnerTest extends TestCase
{

    /**
     * @covers ::parseIdList
     *
     * @dataProvider dataProviderParseIdList
     *
     * @param string $idList
     * @param array $expected
     */
    public function testParseIdList(string $idList, array $expected): void
    {
        $this->assertSame($expected, MigrateUtils::parseIdList($idList));
    }

    /**
     * Data provider for testBuildIdList.
     *
     * @return array
     */
    public function dataProviderParseIdList(): array
    {
        return [
          'empty' => [
            '',
            [],
          ],
          'single simple ID' => [
            '223',
            [['223']],
          ],
          'single ID with delimiters' => [
            '"223,3425"',
            [['223,3425']],
          ],
          'multiple IDs' => [
            '1, 2 ,33,777,4',
            [['1'], ['2'], ['33'], ['777'], ['4']],
          ],
          'multiple with multiple columns' => [
            '1:foo,235:bar, 543:"x:o"',
            [['1', 'foo'], ['235', 'bar'], ['543', 'x:o']],
          ],
        ];
    }
}
