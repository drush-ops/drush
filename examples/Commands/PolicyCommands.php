<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Commands\core\RsyncCommands;
use Drush\Commands\core\UpdateDBCommands;
use Drush\Commands\DrushCommands;
use Drush\Commands\sql\SqlSyncCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Load this commandfile using the --include option - e.g. `drush --include=/path/to/drush/examples`
 *
 * See [Drush Test Traits](https://github.com/drush-ops/drush/blob/12.x/docs/contribute/unish.md#about-the-test-suites) for info on testing Drush commands.
 */

class PolicyCommands extends DrushCommands
{
    /**
     * Prevent catastrophic braino. Note that this file has to be local to the
     * machine that initiates the sql:sync command.
     *
     * @throws \Exception
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: SqlSyncCommands::SYNC)]
    public function sqlSyncValidate(CommandData $commandData)
    {
        if ($commandData->input()->getArgument('destination') == '@prod') {
            throw new \Exception(dt('Per !file, you may never overwrite the production database.', ['!file' => __FILE__]));
        }
    }

    /**
     * Limit rsync operations to production site.
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: RsyncCommands::RSYNC)]
    public function rsyncValidate(CommandData $commandData)
    {
        if (preg_match("/^@prod/", $commandData->input()->getArgument('destination'))) {
            throw new \Exception(dt('Per !file, you may never rsync to the production site.', ['!file' => __FILE__]));
        }
    }

    /**
     * Unauthorized may not execute updates.
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: UpdateDBCommands::UPDATEDB)]
    public function validateUpdateDb(CommandData $commandData)
    {
        if (!$commandData->input()->getOption('secret') == 'mysecret') {
            throw new \Exception(dt('UpdateDb command requires a secret token per site policy.'));
        }
    }

    #[CLI\Hook(type: HookManager::OPTION_HOOK, target: UpdateDBCommands::UPDATEDB)]
    #[CLI\Option(name: 'secret', description: 'A required token else user may not run updatedb command.')]
    public function optionsetUpdateDb($options = ['secret' => self::REQ])
    {
    }
}
