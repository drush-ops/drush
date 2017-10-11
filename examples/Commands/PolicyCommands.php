<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Load this commandfile using the --include option - e.g. `drush --include=/path/to/drush/examples`
 */

class PolicyCommands extends DrushCommands {

  /**
   * Prevent catastrophic braino. Note that this file has to be local to the
   * machine that intitiates the sql-sync command.
   *
   * hook validate sql-sync
   */
  public function sqlSyncValidate(CommandData $commandData) {
    if ($commandData->input()->getArgument('destination') == '@prod') {
      throw new \Exception(dt('Per !file, you may never overwrite the production database.', ['!file' => __FILE__]));
    }
  }

  /**
   * Limit rsync operations to production site.
   *
   * hook validate core-rsync
   */
  public function rsyncValidate(CommandData $commandData) {
    if (preg_match("/^@prod/", $commandData->input()->getArgument('destination'))) {
      throw new \Exception(dt('Per !file, you may never rsync to the production site.', ['!file' => __FILE__]));
    }
  }

  /**
   * Unauthorized may not execute updates.
   *
   * @hook validate updatedb
   */
  public function validateUpdateDb(CommandData $commandData) {
    if (!$commandData->input()->getOption('secret') == 'mysecret') {
      throw new \Exception(dt('UpdateDb command requires a secret token per site policy.'));
    }
  }

  /**
   * @hook option updatedb
   * @option secret A required token else user may not run updatedb command.
   */
  public function optionsetUpdateDb($options = ['secret' => InputOption::VALUE_REQUIRED]) {}
}
