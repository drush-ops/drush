<?php

declare(strict_types=1);

namespace Drush\Preflight;

use PHPUnit\Framework\Attributes\DataProvider;
use Unish\Utils\Fixtures;
use PHPUnit\Framework\TestCase;

final class ArgsPreprocessorTest extends TestCase
{
    use Fixtures;

    #[DataProvider('argTestValues')]
    public function testArgPreprocessor(
        array $argv,
        ?string $alias,
        ?string $selectedSite,
        array $configPath,
        array $aliasPath,
        array $commandPath,
        ?bool $isLocal,
        string $unprocessedArgs
    ): void {


        $argProcessor = new ArgsPreprocessor();
        $preflightArgs = new PreflightArgs();
        $preflightArgs->setHomeDir($this->environment()->homeDir());
        $argProcessor->parse($argv, $preflightArgs);

        $this->assertSame($unprocessedArgs, implode(',', $preflightArgs->args()));
        $this->assertEquals($alias, $preflightArgs->alias());
        $this->assertEquals($selectedSite, $preflightArgs->selectedSite());
        $this->assertEquals($configPath, $preflightArgs->configPaths());
        $this->assertEquals($aliasPath, $preflightArgs->aliasPaths());
    }

    public static function argTestValues(): \Iterator
    {
        yield [
            [
                'drush',
                '@alias',
                'status',
                'version',
            ],

            '@alias',
            null,
            [],
            [],
            [],
            null,
            'drush,status,version',
        ];
        yield [
            [
                'drush',
                '#multisite',
                'status',
                'version',
            ],

            '#multisite',
            null,
            [],
            [],
            [],
            null,
            'drush,status,version',
        ];
        yield [
            [
                'drush',
                'user@server/path',
                'status',
                'version',
            ],

            'user@server/path',
            null,
            [],
            [],
            [],
            null,
            'drush,status,version',
        ];
        yield [
            [
                'drush',
                'rsync',
                '@from',
                '@to',
                '--delete',
            ],

            null,
            null,
            [],
            [],
            [],
            null,
            'drush,rsync,@from,@to,--delete',
        ];
        yield [
            [
                'drush',
                '--root',
                '/path/to/drupal',
                'status',
                '--verbose',
            ],

            null,
            '/path/to/drupal',
            [],
            [],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                '--root=/path/to/drupal',
                'status',
                '--verbose',
            ],

            null,
            '/path/to/drupal',
            [],
            [],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--config',
                '/path/to/config',
            ],

            null,
            null,
            ['/path/to/config'],
            [],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--config=/path/to/config',
            ],

            null,
            null,
            ['/path/to/config'],
            [],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--config=/path/to/config',
                '--config=/other/path/to/config',
            ],

            null,
            null,
            ['/path/to/config','/other/path/to/config'],
            [],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--alias-path',
                '/path/to/aliases',
            ],

            null,
            null,
            [],
            ['/path/to/aliases'],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--alias-path=/path/to/aliases',
            ],

            null,
            null,
            [],
            ['/path/to/aliases'],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--alias-path=/path/to/aliases',
                '--alias-path=/other/path/to/aliases',
            ],

            null,
            null,
            [],
            ['/path/to/aliases','/other/path/to/aliases'],
            [],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--include',
                '/path/to/commands',
            ],

            null,
            null,
            [],
            [],
            ['path/to/commands'],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--include=/path/to/commands',
            ],

            null,
            null,
            [],
            [],
            ['path/to/commands'],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--include=/path/to/commands',
            ],

            null,
            null,
            [],
            [],
            ['path/to/commands'],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--include=/path/to/commands',
                '--include=/other/path/to/commands',
            ],

            null,
            null,
            [],
            [],
            ['path/to/commands','/other/path/to/commands'],
            null,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                'status',
                '--verbose',
                '--local',
            ],

            null,
            null,
            [],
            [],
            [],
            true,
            'drush,status,--verbose',
        ];
        yield [
            [
                'drush',
                '@alias',
                'status',
                '--verbose',
                '--local',
                '--alias-path=/path/to/aliases',
                '--config=/path/to/config',
                '--root=/path/to/drupal',
                '--include=/path/to/commands',
            ],

            '@alias',
            '/path/to/drupal',
            ['/path/to/config'],
            ['/path/to/aliases'],
            ['path/to/commands'],
            true,
            'drush,status,--verbose',
        ];
    }
}
