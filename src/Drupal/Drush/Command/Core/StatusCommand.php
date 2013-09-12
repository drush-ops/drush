<?php

namespace Drupal\Drush\Command\Core;

use Drupal\Drush\Version;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require dirname(__FILE__) . '/../../../../../commands/core/core.drush.inc';

class StatusCommand extends Command
{
  protected function configure()
  {
    $this
      ->setName('core:status')
      ->setDescription('Provides a birds-eye view of the current Drupal installation, if any.')
      ->setAliases(array(
        'status',
        'st',
      ))
      ->addArgument(
        'item', InputArgument::OPTIONAL,
        'The status item line(s) to display.'
      )
      ->addOption(
        'fields', null, InputOption::VALUE_REQUIRED,
        'Fields to output.'
      )
      ->addOption(
        'format', null, InputOption::VALUE_REQUIRED,
        'Select output format. Available: json, list, var_export, yaml. Default is key-value.',
        'key-value'
      )
      ->addOption(
        'full', null, InputOption::VALUE_NONE,
        'Show all file paths and drush aliases in the report, even if there are a lot.'
      )
      ->addOption(
        'pipe', null, InputOption::VALUE_NONE,
        'Equivalent to --format=json.'
      )
      ->addOption(
        'project', null, InputOption::VALUE_REQUIRED,
        'One or more projects that should be added to the path list.'
      )
      ->addOption(
        'show-passwords', null, InputOption::VALUE_NONE,
        'Show database passwords.'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    drush_bootstrap_prepare();
    $status = drush_core_status();
    $output->writeln(print_r($status));
  }
}
