<?php

include_once(DRUSH_BASE_PATH . '/commands/core/status.drush.inc')

namespace Drupal\Drush\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

ass StatusCommand extends Command {
  protected function configure() {
    $this
      ->setName('status')
      ->setDescription('Provides a birds-eye view of the current Drupal installation, if any.')
      ->addArgument(
        'item',
        InputArgument::OPTIONAL,
        'The status item line(s) to display.'
      )
      ->addOption(
        'full',
        null,
        InputOption::VALUE_NONE,
        'Show all file paths and drush aliases in the report, even if there are a lot.'
      )
      ->addOption(
        'project',
        null,
        InputOption::VALUE_NONE,
        'One or more projects that should be added to the path list. Ex. foo,bar'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $text = drush_core_status();
    $output->writeln($text)
  }
}
