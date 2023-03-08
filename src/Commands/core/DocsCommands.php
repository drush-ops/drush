<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Topic commands.
 * Any commandfile may add topics.
 * Us ethe Topic attribute to indicate the command is a topic
 */
final class DocsCommands extends DrushCommands
{
    /**
     * README.md
     */
    #[CLI\Command(name: 'docs:readme', aliases: ['docs-readme'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../README.md')]
    public function readme(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush docs for Git Bisect.
     */
    #[CLI\Command(name: 'docs:bisect', aliases: ['docs-bisect'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../examples/git-bisect.example.sh')]
    public function bisect(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush configuration.
     */
    #[CLI\Command(name: 'docs:configuration', aliases: ['docs-configuration'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/using-drush-configuration.md')]
    public function config(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush hooks.
     */
    #[CLI\Command(name: 'docs:hooks', aliases: ['docs-hooks'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/hooks.md')]
    public function hooks(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drupal config export instructions, including customizing config by environment.
     */
    #[CLI\Command(name: 'docs:config:exporting', aliases: ['docs-config-exporting'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/config-exporting.md')]
    public function configExport(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Output formatters and filters: control the command output
     */
    #[CLI\Command(name: 'docs:output-formats-filters', aliases: ['docs:output'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/output-formats-filters.md')]
    public function outputFormatsFilters(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Creating site aliases for running Drush on remote sites.
     */
    #[CLI\Command(name: 'docs:aliases', aliases: ['docs-aliases'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/site-aliases.md')]
    public function siteAliases(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * An example Drush script.
     */
    #[CLI\Command(name: 'docs:script', aliases: ['docs-script'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../examples/helloworld.script')]
    public function script(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Bootstrap explanation: how Drush starts up and prepares the Drupal environment.
     *
     * @command docs:bootstrap
     * @aliases docs-bootstrap
     * @hidden
     * @topic ../../../docs/bootstrap.md
     */
    #[CLI\Command(name: 'docs:bootstrap', aliases: ['docs-bootstrap'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/bootstrap.md')]
    public function bootstrap(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Crontab instructions for running your Drupal cron tasks via `drush cron`.
     */
    #[CLI\Command(name: 'docs:cron', aliases: ['docs-cron'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/cron.md')]
    public function cron(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Instructions on creating your own Drush commands.
     */
    #[CLI\Command(name: 'docs:commands', aliases: ['docs-commands'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/commands.md')]
    public function commands(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Instructions on creating your own Drush Generators.
     */
    #[CLI\Command(name: 'docs:generators', aliases: ['docs-generators'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/generators.md')]
    public function generators(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Example Drush command file.
     */
    #[CLI\Command(name: 'docs:examplecommand', aliases: ['docs-examplecommand'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../examples/Commands/ArtCommands.php')]
    public function exampleCommand(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Defining and running migrations.
     */
    #[CLI\Command(name: 'docs:migrate')]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/migrate.md')]
    public function migrate(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Extend sql-sync to allow transfer of the sql dump file via http.
     */
    #[CLI\Command(name: 'docs:example-sync-via-http', aliases: ['docs-example-sync-via-http'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../examples/Commands/SyncViaHttpCommands.php')]
    public function syncHttp(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Example policy file.
     */
    #[CLI\Command(name: 'docs:policy', aliases: ['docs-policy'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../examples/Commands/PolicyCommands.php')]
    public function policy(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Deploy command for Drupal.
     */
    #[CLI\Command(name: 'docs:deploy', aliases: ['docs-deploy'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topic(path: '../../../docs/deploycommand.md')]
    public function deploy(): void
    {
        self::printFileTopic($this->commandData);
    }
}
