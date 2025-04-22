<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drush\Attributes as CLI;

final class LegacyCommands extends DrushCommands
{
    /**
     * archive:restore has been removed. A replacement may be available from your web host.
     */
    #[CLI\Command(name: 'archive:restore', aliases: ['arr'])]
    #[CLI\Obsolete]
    public function archiveRestore(): void
    {
    }

    /**
     * site:alias-convert has been removed. Please use Drush 11 or convert by hand.
     */
    #[CLI\Command(name: 'site:alias-convert', aliases: ['sa-convert', 'sac'])]
    #[CLI\Obsolete]
    public function saconvert(): void
    {
    }

    /**
     * pm:security-php has been removed. Please use `composer audit` command.
     */
    #[CLI\Command(name: 'pm:security-php', aliases: ['sec-php', 'pm-security-php'])]
    #[CLI\Obsolete]
    public function secphp(): void
    {
    }

    /**
     * pm:security has been removed. Please use `composer audit` command. See https://www.drupal.org/project/project_composer/issues/3301876.
     */
    #[CLI\Command(name: 'pm:security', aliases: ['sec', 'pm-security'])]
    #[CLI\Obsolete]
    public function sec(): void
    {
    }

    /**
     * twig:debug has been removed. Please use the `theme:dev` command.
     */
    #[CLI\Command(name: 'twig:debug', aliases: ['twig-debug'])]
    #[CLI\Obsolete]
    public function twigDebug(): void
    {
    }
}
