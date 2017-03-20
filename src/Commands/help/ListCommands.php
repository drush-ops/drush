<?php
namespace Drush\Commands\help;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

class ListCommands extends DrushCommands {

  /**
   * List available commands.
   *
   * @command list
   * @param $filter Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   * @usage drush list
   *   List all commands.
   * @usage drush list --filter=devel_generate
   *   Show only commands starting with devel-
   *
   * @return array
   */
  public function helpList($filter, $options = ['format' => 'listcli']) {
    $application = \Drush::getApplication();
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

    // Avoid solo namespaces.
    foreach ($namespaced as $namespace => $commands) {
      if (count($commands) == 1) {
        $namespaced['other'] += $commands;
        unset($namespaced[$namespace]);
      }
    }

    ksort($namespaced);

    // Filter out namespaces that the user does not want to see
    $filter_category = $options['filter'];
    if (!empty($filter_category)) {
      if (!array_key_exists($filter_category, $namespaced)) {
        throw new \Exception(dt("The specified command category !filter does not exist.", array('!filter' => $filter_category)));
      }
      $namespaced = array($filter_category => $namespaced[$filter_category]);
    }

    // This serves as example about how a command can add a custom Formatter.
    $formatter = new ListCLIFormatter();
    $formatterManager = \Drush::getContainer()->get('formatterManager');
    $formatterManager->addFormatter('listcli', $formatter);

    return $namespaced;
  }
}
