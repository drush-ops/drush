<?php

namespace Drush\Command;

use Drush\Drush;

enum HelpLinks
{
    case Aliases;
    case DrushConfiguration;
    case Policy;
    case Deploy;
    case ConfigExporting;
    case Repl;
    case Cron;
    case Migrate;
    case Script;
    case Readme;
    case Generators;
    case SyncViaHttp;
    case Events;

    public function getConsoleLink(): ConsoleLink
    {
        return match ($this) {
            self::Aliases => new ConsoleLink('site-aliases', 'Creating site aliases for running Drush on remote sites'),
            self::Deploy => new ConsoleLink('deploy', 'Deploy command for Drupal.'),
            self::DrushConfiguration => new ConsoleLink('using-drush-configuration', 'Drush configuration'),
            self::Policy => new ConsoleLink('examples/PolicyListener.php', 'Example policy file'),
            self::ConfigExporting => new ConsoleLink('config-exporting', 'Drupal config export instructions, including customizing config by environment.'),
            self::Repl => new ConsoleLink('repl', 'Drush\'s PHP Shell'),
            self::Cron => new ConsoleLink('cron', 'Crontab instructions for running your Drupal cron tasks via `drush cron`.'),
            self::Migrate => new ConsoleLink('migrate', 'Defining and running migrations.'),
            self::Script => new ConsoleLink('examples/helloworld.script', 'An example Drush script'),
            self::SyncViaHttp => new ConsoleLink('examples/Listeners/SyncViaHttpListener.php', 'Extend sql-sync to allow transfer of the sql dump file via http.'),
            self::Readme => new ConsoleLink('README.md', 'README.md'),
            self::Generators => new ConsoleLink('generators', 'Instructions on creating your own Drush Generators.'),
            self::Events => new ConsoleLink('hooks', 'Event dispatching and listening.'),
        };
    }

    /**
     * A base URL for help links.
     */
    public static function getDocsUrlBase(): string
    {
        $branch = Drush::getMajorVersion() . '.x';
        return "https://www.drush.org/$branch";
    }

    /**
     * Build Console hyperlink to a Drush docs page.
     */
    public function consoleLink(): string
    {
        $link = $this->getConsoleLink();
        return sprintf('  * <href=%s/%s>%s</>', self::getDocsUrlBase(), $link->path, $link->text);
    }
}
