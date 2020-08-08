<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;

/**
 * Topic commands.
 * Any commandfile may add topics.
 * Set 'topic' => TRUE to indicate the command is a topic (REQUIRED)
 * Begin the topic name with the name of the commandfile (just like
 * any other command).
 */
class DocsCommands extends DrushCommands
{
    /**
     * README.md
     *
     * @command docs:readme
     * @aliases docs-readme
     * @hidden
     * @topic ../../../README.md
     */
    public function readme()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush's support for Git Bisect.
     *
     * @command docs:bisect
     * @aliases docs-bisect
     * @hidden
     * @topic ../../../examples/git-bisect.example.sh
     */
    public function bisect()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Bashrc customization examples for Drush.
     *
     * @command docs:bashrc
     * @aliases docs-bashrc
     * @hidden
     * @topic ../../../examples/example.bashrc
     */
    public function bashrc()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush configuration example.
     *
     * @command docs:configuration
     * @aliases docs-configuration
     * @hidden
     * @topic ../../../examples/example.drush.yml
     */
    public function config()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush hooks.
     *
     * @command docs:hooks
     * @aliases docs-hooks
     * @hidden
     * @topic ../../../docs/hooks.md
     */
    public function hooks()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drupal config export instructions, including customizing config by environment.
     *
     * @command docs:config:exporting
     * @aliases docs-config-exporting
     * @hidden
     * @topic ../../../docs/config-exporting.md
     */
    public function configExport()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Output formatters and filters: control the command output
     *
     * @command docs:output-formats-filters
     * @aliases docs:output
     * @aliases docs-output
     * @hidden
     * @topic  ../../../docs/output-formats-filters.md
     */
    public function outputFormatsFilters()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Creating site aliases for running Drush on remote sites.
     *
     * @command docs:aliases
     * @aliases docs-aliases
     * @hidden
     * @topic ../../../examples/example.site.yml
     */
    public function siteAliases()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Bootstrap explanation: how Drush starts up and prepares the Drupal environment.
     *
     * @command docs:bootstrap
     * @aliases docs-bootstrap
     * @hidden
     * @topic ../../..//docs/bootstrap.md
     */
    public function bootstrap()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Crontab instructions for running your Drupal cron tasks via `drush cron`.
     *
     * @command docs:cron
     * @aliases docs-cron
     * @hidden
     * @topic ../../../docs/cron.md
     */
    public function cron()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Instructions on creating your own Drush commands.
     *
     * @command docs:commands
     * @aliases docs-commands
     * @hidden
     * @topic ../../../docs/commands.md
     */
    public function commands()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Instructions on creating your own Drush Generators.
     *
     * @command docs:generators
     * @aliases docs-generators
     * @hidden
     * @topic ../../../docs/generators.md
     */
    public function generators()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Example Drush command file.
     *
     * @command docs:examplecommand
     * @aliases docs-examplecommand
     * @hidden
     * @topic ../../../examples/Commands/ArtCommands.php
     */
    public function exampleCommand()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Extend sql-sync to allow transfer of the sql dump file via http.
     *
     * @command docs:example-sync-via-http
     * @aliases docs-example-sync-via-http
     * @hidden
     * @topic ../../../examples/Commands/SyncViaHttpCommands.php
     */
    public function syncHttp()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Example policy file.
     *
     * @command docs:policy
     * @aliases docs-policy
     * @hidden
     * @topic ../../../examples/Commands/PolicyCommands.php
     */
    public function policy()
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Deploy command for Drupal.
     *
     * @command docs:deploy
     * @aliases docs-deploy
     * @hidden
     * @topic  ../../../docs/deploycommand.md
     */
    public function deploy()
    {
        self::printFileTopic($this->commandData);
    }
}
