<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drush\Attributes as CLI;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Works like php-eval. Used for testing $command_specific context.',
    aliases: ['unit-roc', 'unit-return-options'],
    hidden: true,
)]
#[CLI\OptionsetTableSelection]
final class UnitReturnOptionsCommand extends Command
{
    public const NAME = 'unit:roc';

    public function configure(): void {
      $this
        ->addArgument(name: 'code', description: 'Code you wish to run');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // @todo deal with formatters.
        eval($input->getArgument('code') . ';');

        return Command::SUCCESS;
    }
}
