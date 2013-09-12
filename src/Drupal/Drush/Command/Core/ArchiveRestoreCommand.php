<?php

namespace Drupal\Drush\Command\Core;

use Drupal\Drush\Version;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ArchiveRestoreCommand extends Command
{
  protected function configure()
  {
    $this
      ->setName('core:archive-restore')
      ->setDescription('Expand a site archive into a Drupal web site.')
      ->setAliases(array(
        'archive-restore',
        'arr',
      ))
      ->addArgument(
        'file', InputArgument::REQUIRED,
        'The site archive file that should be expanded.'
      )
      ->addArgument(
        'site name', InputArgument::OPTIONAL,
        'Which site within the archive you want to restore.',
        'all'
      )
      ->addOption(
        'db-prefix', null, InputOption::VALUE_REQUIRED,
        'An optional table prefix to use during restore.'
      )
      ->addOption(
        'db-su', null, InputOption::VALUE_REQUIRED,
        'Account to use when creating the new database. Optional.'
      )
      ->addOption(
        'db-su-pw', null, InputOption::VALUE_REQUIRED,
        'Password for the "db-su" account. Optional.'
      )
      ->addOption(
        'db-url', null, InputOption::VALUE_REQUIRED,
        'A Drupal 6 style database URL indicating the target for database restore. If not provided, the archived settings.php is used.'
      )
      ->addOption(
        'destination', null, InputOption::VALUE_REQUIRED,
        'Specify where the Drupal site should be expanded, including the docroot. Defaults to the current working directory.'
      )
      ->addOption(
        'overwrite', null, InputOption::VALUE_NONE,
        'Allow drush to overwrite any files in the destination.'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $text = 'Hello!';
    $output->writeln($text);
  }
}
