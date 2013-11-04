<?php

namespace Drupal\Drush\Command\Core;

use Drupal\Drush\Version;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ArchiveDumpCommand extends Command
{
  protected function configure()
  {
    $this
      ->setName('core:archive-dump')
      ->setDescription('Backup your code, files, and database into a single file.')
      ->setAliases(array(
        'archive-dump',
        'ard',
        'archive-backup',
        'arb'
      ))
      ->addArgument(
        'sites', InputArgument::OPTIONAL,
        'Site specifications, delimited by commas. Typically, list subdirectory name(s) under /sites.'
      )
      ->addOption(
        'description', null, InputOption::VALUE_REQUIRED,
        'Describe the archive contents.'
      )
      ->addOption(
        'destination', null, InputOption::VALUE_REQUIRED,
        'The full path and filename in which the archive should be stored. If omitted, it will be saved to the drush-backups directory and a filename will be generated.'
      )
      ->addOption(
        'generator', null, InputOption::VALUE_REQUIRED,
        'The generator name to store in the MANIFEST file. The default is "Drush archive-dump".'
      )
      ->addOption(
        'generatorversion', null, InputOption::VALUE_REQUIRED,
        "The generator version number to store in the MANIFEST file. The default is " . Version::VERSION
      )
      ->addOption(
        'no-core', null, InputOption::VALUE_NONE,
        'Exclude Drupal core, so the backup only contains the site specific stuff.'
      )
      ->addOption(
        'overwrite', null, InputOption::VALUE_NONE,
        'Do not fail if the destination file exists; overwrite it instead.'
      )
      ->addOption(
        'pipe', null, InputOption::VALUE_NONE,
        "Only print the destination of the archive. Useful for scripts that don't pass --destination."
      )
      ->addOption(
        'preserve-symlinks', null, InputOption::VALUE_NONE,
        'Preserve symbolic links.'
      )
      ->addOption(
        'tags', null, InputOption::VALUE_REQUIRED,
        'Add tags to the archive manifest. Delimit multiple by commas.'
      )
      ->addOption(
        'tar-options', null, InputOption::VALUE_REQUIRED,
        'Options passed thru to the tar command.'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $text = 'Hello!';
    $output->writeln($text);
  }
}
