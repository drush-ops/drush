<?php
namespace Drush\Commands\internal;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\ListCommand;

class ListCommands extends DrushCommands {

  /**
   * @command list
   * @param $filter Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   * @usage drush list
   *   List all commands.
   * @usage drush list --filter=devel_generate
   *   Show only commands starting with devel-
   */
  public function helpList($filter, $options = ['format' => 'table']) {
    /** @var Application $application */
    $application = \Drush::getContainer()->get('application');
    $all = $application->all();

    foreach ($all as $key => $command) {
      /** @var \Consolidation\AnnotatedCommand\AnnotationData $annotationData */
      $annotationData = $command->getAnnotationData();
      if (!in_array($key, $command->getAliases()) && !$annotationData->has('hidden')) {
        $parts = explode('-', $key);
        $namespace = count($parts) >= 2 ? array_shift($parts) : 'other';
        $namespaced[$namespace][$key] = $command;
      }
    }

    if ($options['format'] != 'table') {
      // @todo - send something other that Command instances.
      return $namespaced;
    }
    else {

      // Filter out categories that the user does not want to see
      $filter_category = $options['filter'];
      if (!empty($filter_category) && ($filter_category !== TRUE)) {
        if (!array_key_exists($filter_category, $namespaced)) {
          throw new \Exception(dt("The specified command category !filter does not exist.", array('!filter' => $filter_category)));
        }
        $namespaced = array($filter_category => $namespaced[$filter_category]);
      }

      // Make a fake command section to hold the global options, then print it.
      $global_options_help = drush_global_options_command(TRUE);
      if (!$options['filter']) {
        drush_print_help($global_options_help);
      }
      drush_help_listing_print($command_categories);
      drush_backend_set_result($command_categories);
      return;
    }
  }
}
