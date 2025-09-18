<?php

namespace Drush\Command;

enum HelpLinks
{
    case Aliases;
    case DrushConfiguration;
    case Policy;
    case Deploy;

    public function getConsoleLink(): ConsoleLink
    {
        return match ($this) {
            self::Aliases => new ConsoleLink('site-aliases', 'Creating site aliases for running Drush on remote sites'),
            self::Deploy => new ConsoleLink('deploy', 'Deploy command for Drupal.'),
            self::DrushConfiguration => new ConsoleLink('using-drush-configuration', 'Drush configuration'),
            self::Policy => new ConsoleLink('examples/PolicyCommands.php', 'Example policy file'),
        };
    }

    /**
     * A base URL for help links.
     */
    public static function getDocsUrlBase($branch = 'latest'): string
    {
        return "https://www.drush.org/$branch";
    }

    /**
     * Build Console hyperlink to a Drush docs page.
     */
    public function consoleLink(): string
    {
        $link = $this->getConsoleLink();
        return sprintf('* <href=%s/%s>%s</>', self::getDocsUrlBase(), $link->path, $link->text);
    }
}
