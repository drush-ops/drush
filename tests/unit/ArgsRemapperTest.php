<?php

namespace Unish;

use Drush\Preflight\ArgsRemapper;
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
            'in' => 'pm:install'
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
                ['in', 'in'],
                ['install', 'install'],
                ['pm-install', 'pm-install'],
                ['pm:install', 'install'],
                ['en', 'en'],
                ['pm-enable', 'pm-enable'],
                ['pm:enable', 'en'],
            ],
        ];
    }
}
