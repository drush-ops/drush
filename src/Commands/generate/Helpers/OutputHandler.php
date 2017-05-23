<?php

namespace Drush\Commands\generate\Helpers;

use Symfony\Component\Console\Output\OutputInterface;
use DrupalCodeGenerator\Helpers\OutputHandler as BaseOutputHandler;
use Webmozart\PathUtil\Path;

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
      $file = Path::join($directory, $file);
    }

    if (defined('DRUPAL_ROOT')) {
      // @todo Below code is forking new process well but current process is not shutting down fully.
      $exec = drush_get_editor();
      $exec = str_replace('%s', drush_escapeshellarg(Path::makeAbsolute($dumped_files[0], DRUPAL_ROOT)), $exec);
      $pipes = array();
      proc_close(proc_open($exec  . ' 2> ' . drush_bit_bucket() . ' &', array(), $pipes));
    }

    parent::printSummary($output, $dumped_files);
  }

}
