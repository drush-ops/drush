<?php
namespace Drush\Commands\help;

use Consolidation\OutputFormatters\Formatters\FormatterInterface;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

/**
 * Format an array into CLI list string.
 */
class ListCLIFormatter implements FormatterInterface
{

  /**
   * @inheritdoc
   */
  public function write(OutputInterface $output, $data, FormatterOptions $options)
  {
    $table = new Table($output);
    $table->setStyle('compact');

    // $output->writeln($data['description']);
    // @todo Bring back global help.
    // Make a fake command section to hold the global options, then print it.
    //      $global_options_help = drush_global_options_command(TRUE);
    //      if (!$options['filter']) {
    //        drush_print_help($global_options_help);
    //      }
    foreach ($data as $namespace => $list) {
      $table->addRow([new TableCell($namespace . ':', array('colspan' => 2))]);
      foreach ($list as $name => $command) {
        $description = $command->getDescription();
        $aliases = implode(', ', $command->getAliases());
        $suffix = $aliases ? " ($aliases)" : '';
        $table->addRow(['  ' . $name . $suffix, $description]);
      }
    }
    $table->render();
  }
}
