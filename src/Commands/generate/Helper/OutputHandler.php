<?php

namespace Drush\Commands\generate\Helper;

use Consolidation\SiteProcess\Util\Escape;
use DrupalCodeGenerator\Helper\OutputHandler as BaseOutputHandler;
use Drush\Drush;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * Output printer form generators.
 */
class OutputHandler extends BaseOutputHandler
{

    /**
     * {@inheritdoc}
     */
    public function printSummary(OutputInterface $output, array $dumped_files)
    {
        /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
        $command = $this->getHelperSet()->getCommand();
        $directory = $command->getDirectory();

        // Make the paths relative to Drupal root directory.
        foreach ($dumped_files as &$file) {
            $file = Path::join($directory, $file);
        }

        if (defined('DRUPAL_ROOT') && $dumped_files) {
            $exec = drush_get_editor();
            $exec = str_replace('%s', Escape::shellArg(Path::makeAbsolute($dumped_files[0], DRUPAL_ROOT)), $exec);
            $process = Drush::shell($exec);
            // Use start() in order to get an async fork.
            $process->start();
        }
        parent::printSummary($output, $dumped_files);
    }
}
