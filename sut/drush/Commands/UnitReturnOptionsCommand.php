<?php

declare(strict_types=1);

namespace Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Works like php-eval. Used for testing $command_specific context.',
    aliases: ['unit-roc', 'unit-return-options'],
    hidden: true,
)]
#[CLI\OptionsetTableSelection]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\Formatter(defaultFormatter: 'var_dump')]
final class UnitReturnOptionsCommand extends Command
{
    public const NAME = 'unit:roc';

    use AutowireTrait;
    use FormatterTrait;


  public function __construct(
    protected readonly BootstrapManager $bootstrapManager,
    protected readonly FormatterManager $formatterManager,
  ) {
    parent::__construct();
  }


  public function configure(): void {
        $this
          ->addArgument(name: 'code', mode: InputArgument::REQUIRED, description: 'Code you wish to run');
      }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $data = $this->doExecute($input, $output);
    $this->writeFormattedOutput($input, $output, $data);
    return self::SUCCESS;
  }

  public function doExecute(InputInterface $input, OutputInterface $output): mixed
  {
    $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);

    return eval($input->getArgument('code') . ';');
  }
}
