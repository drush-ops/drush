<?php
namespace Drush\Preflight;

use PHPUnit\Framework\TestCase;

class ArgsPreprocessorTest extends TestCase
{
    /**
     * @dataProvider argTestValues
     */
    public function testArgPreprocessor(
        $argv,
        $alias,
        $selectedSite,
        $configPath,
        $aliasPath,
        $commandPath,
        $isLocal,
        $unprocessedArgs)
    {
        $home = __DIR__ . '/fixtures/home';

        $argProcessor = new ArgsPreprocessor($home);
        $preflightArgs = new PreflightArgs();
        $argProcessor->parse($argv, $preflightArgs);

        $this->assertEquals($unprocessedArgs, implode(',', $preflightArgs->args()));
        $this->assertEquals($alias, $preflightArgs->alias());
        $this->assertEquals($selectedSite, $preflightArgs->selectedSite());
        $this->assertEquals($configPath, $preflightArgs->configPaths());
        $this->assertEquals($aliasPath, $preflightArgs->aliasPaths());
    }

    public static function argTestValues()
    {
        return [
            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],

            [
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
            ],
        ];
    }
}
