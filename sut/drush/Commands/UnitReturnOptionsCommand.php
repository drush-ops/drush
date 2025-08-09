<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drush\Attributes as CLI;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: self::NAME,
    description: 'Works like php-eval. Used for testing $command_specific context.',
    // @todo needs bug fix that will be in Console 7.4+
    // hidden: true,
    // bug somewhere. alias is becoming part of the command name in the Application
    // aliases: ['unit-roc'],
)]
#[CLI\OptionsetTableSelection]
final class UnitReturnOptionsCommand
{
    public const NAME = 'unit:roc';

    public function __invoke(
        #[Argument(description: 'Code you wish to run')] string $code,
    ): int
    {
        // @todo deal with formatters.
        eval($code . ';');

        return Command::SUCCESS;
    }
}
