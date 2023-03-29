<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drush\Attributes as CLI;

final class LegacyCommands extends DrushCommands
{
    /**
     * site:alias-convert has been removed. Please use Drush 11 or convert by hand.
     */
    #[CLI\Command(name: 'site:alias-convert', aliases: ['sa-convert', 'sac'])]
    #[CLI\Help(hidden: true)]
    #[CLI\HookSelector(name: 'obsolete')]
    public function saconvert(): void
    {
    }

    /**
     * pm:security-php has been removed. Please use `composer audit` command.
     */
    #[CLI\Command(name: 'pm:security-php', aliases: ['sec-php', 'pm-security-php'])]
    #[CLI\Help(hidden: true)]
    #[CLI\HookSelector(name: 'obsolete')]
    public function secphp(): void
    {
    }
}
