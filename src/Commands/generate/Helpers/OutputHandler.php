<?php

namespace Drush\Commands\generate\Helpers;

use Symfony\Component\Console\Output\OutputInterface;
use DrupalCodeGenerator\Helpers\OutputHandler as BaseOutputHandler;

/**
 * Output printer form generators.
 */
class OutputHandler extends BaseOutputHandler {

  /**
   * {@inheritdoc}
   */
  public function printSummary(OutputInterface $output, array $dumped_files) {

    /** @var \DrupalCodeGenerator\Commands\GeneratorInterface $command */
    $command = $this->getHelperSet()->getCommand();
    $directory = $command->getDirectory();

    // Make the paths relative to Drupal root directory.
    foreach ($dumped_files as &$file) {
      $file = "$directory/$file";
    }

    parent::printSummary($output, $dumped_files);
  }

}
