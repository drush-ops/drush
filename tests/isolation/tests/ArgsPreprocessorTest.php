<?php
namespace Drush\Boot;

class ArgsPreprocessorTest extends \PHPUnit_Framework_TestCase
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
        $unprocessedArgs)
    {
        $home = __DIR__ . '/fixtures/home';

        $argProcessor = new ArgsPreprocessor($home);
        $preflightArgs = new PreflightArgs();
        $argProcessor->parseArgv($argv, $preflightArgs);

        $this->assertEquals($alias, $preflightArgs->alias());
        $this->assertEquals($selectedSite, $preflightArgs->selectedSite());
        $this->assertEquals($configPath, $preflightArgs->configPath());
        $this->assertEquals($aliasPath, $preflightArgs->aliasPath());
        $this->assertEquals($unprocessedArgs, implode(',', $preflightArgs->args()));
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
                null,
                null,
                'status,version',
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
                null,
                null,
                'rsync,@from,@to,--delete',
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
                null,
                null,
                'status,--verbose',
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
                null,
                null,
                'status,--verbose',
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
                '/path/to/config',
                null,
                'status,--verbose',
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
                '/path/to/config',
                null,
                'status,--verbose',
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
                null,
                '/path/to/aliases',
                'status,--verbose',
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
                null,
                '/path/to/aliases',
                'status,--verbose',
            ],

            [
                [
                    'drush',
                    'status',
                    '--verbose',
                    '--alias-path=/path/to/aliases',
                    '--config=/path/to/config',
                    '--root=/path/to/drupal',
                ],

                null,
                '/path/to/drupal',
                '/path/to/config',
                '/path/to/aliases',
                'status,--verbose',
            ],
        ];
    }
}
