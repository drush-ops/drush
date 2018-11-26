<?php

namespace Unish;

use \Drush\Preflight\ArgsRemapper;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Arguments Remapper.
 *
 * @group base
 */
class ArgsRemapperTest extends TestCase
{

    /**
     * @covers argsRemapper::ArgsRemapper
     * @dataProvider argsProvider
     */
    public function testCommandAliases($argv, $expected)
    {
        $remapOptions = [];
        $remapCommandAliases = [
            'en' => 'pm:enable'
        ];
        $sut = new ArgsRemapper($remapOptions, $remapCommandAliases);
        $result = $sut->remap($argv);

        $this->assertEquals($expected, $result);
    }

    /**
     * Provides arguments for ::ArgsRemapper
     */
    public function argsProvider()
    {
        return [
            [
                ['en', 'en'],
                ['pm:enable', 'en'],
            ],
        ];
    }
}
