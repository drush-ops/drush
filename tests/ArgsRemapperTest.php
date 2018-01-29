<?php

namespace Unish;

use \Drush\Preflight\ArgsRemapper;

/**
 * Tests the Arguments Remapper.
 *
 * @group base
 */
class ArgsRemapperTest extends CommandUnishTestCase
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
     * Provides argumens for ::ArgsRemapper
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
