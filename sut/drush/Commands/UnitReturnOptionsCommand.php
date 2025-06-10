<?php

declare(strict_types=1);

namespace Drush\Commands;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: self::NAME,
    description: 'Works like php-eval. Used for testing $command_specific context.',
    // @todo needs bug fix thats console 7.4+
    // hidden: true,
    aliases: ['unit-eval'],
)]
final class UnitReturnOptionsCommand
{
    public const NAME = 'unit:eval';

    public function __invoke(
        #[Argument(description: 'Code you wish to run')] string $code,
    )
    {
        // @todo deal with formatters.
        eval($code . ';');

        return 0;
    }
}
