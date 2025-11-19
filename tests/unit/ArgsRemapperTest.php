<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drush\Preflight\ArgsRemapper;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Arguments Remapper.
 */
#[Group('base')]
final class ArgsRemapperTest extends TestCase
{
    #[DataProvider('argsProvider')]
    public function testCommandAliases(array $argv, array $expected): void
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
    public static function argsProvider(): \Iterator
    {
        yield [
            ['en', 'en'],
            ['pm:enable', 'en'],
        ];
    }
}
