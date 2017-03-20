<?php
namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Drush\Commands\DrushCommands;

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
   * @return \DOMDocument
   */
  public function helpList($filter, $options = ['format' => 'listcli']) {
    $application = \Drush::getApplication();
    $all = $application->all();

    foreach ($all as $key => $command) {
      /** @var \Consolidation\AnnotatedCommand\AnnotationData $annotationData */
      $annotationData = $command->getAnnotationData();
      if (!in_array($key, $command->getAliases()) && !$annotationData->has('hidden')) {
        $parts = explode('-', $key);
        $namespace = count($parts) >= 2 ? array_shift($parts) : '_global';
        $namespaced[$namespace][$key] = $command;
      }
    }

    // Avoid solo namespaces.
    foreach ($namespaced as $namespace => $commands) {
      if (count($commands) == 1) {
        $namespaced['_global'] += $commands;
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

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $commandsXML = $dom->createElement('commands');
    $namespacesXML = $dom->createElement('namespaces');
    foreach ($namespaced as $namespace => $commands) {
      $helpDocument = new HelpDocument($command);
      $domData = $helpDocument->getDomData();
      $node = $domData->getElementsByTagName("command")->item(0);
      $element = $dom->importNode($node, true);
      $commandsXML->appendChild($element);

      $namespaceXML = $dom->createElement('namespace');
      $namespaceXML->setAttribute('id', $namespace);
      foreach ($commands as $key => $command) {
        $ncommandXML = $dom->createElement('command', $key);
        $namespaceXML->appendChild($ncommandXML);
      }
      $namespacesXML->appendChild($namespaceXML);
    }

    // Append top level elements in correct order.
    $dom->appendChild($commandsXML);
    $dom->appendChild($namespacesXML);

    // This serves as example about how a command can add a custom Formatter.
    $formatter = new ListCLIFormatter();
    $formatterManager = \Drush::getContainer()->get('formatterManager');
    $formatterManager->addFormatter('listcli', $formatter);

    return $dom;
  }
}
