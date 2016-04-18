<?php

/**
 * @file
 * annotationcommand_adapter.inc
 */

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;

/**
 * TODO: maybe configure and cache the CommandFileDiscovery class?
 */
function annotationcommand_adapter_get_discovery() {
  return new CommandFileDiscovery();
}

/**
 * Return the cached commands built by annotationcommand_adapter_discover.
 */
function annotationcommand_adapter_commands() {
  $commands = drush_get_context('DRUSH_ANNOTATED_COMMANDS');
  return $commands;
}

/**
 * Search for annotation commands at the provided search path.
 *
 * @see drush_get_commands()
 */
function annotationcommand_adapter_discover($searchpath) {
  $commands = drush_get_context('DRUSH_ANNOTATED_COMMANDS');
  $factory = new AnnotatedCommandFactory();
  $discovery = annotationcommand_adapter_get_discovery();
  $annotation_commandfiles = $discovery->discoverNamespaced($searchpath, '\Drupal');
  // This will give us a list containing something akin to:
  //   'modules/default_content/src/CliTools/DefaultContentCommands.php' =>
  //   '\\Drupal\\default_content\\CliTools\\DefaultContentCommands',
  foreach ($annotation_commandfiles as $commandfile_path => $commandfile_class) {
    include_once $commandfile_path;
    $commandhandler = new $commandfile_class;
    $commandinfo_list = $factory->getCommandInfoListFromClass($commandhandler);

    foreach ($commandinfo_list as $commandinfo) {
      $command_name = strtolower($commandinfo->getName());
      $command_name = str_replace(':', '-', $command_name);
      $commandfile = ''; // what value should this take?
      $command = [
        'name' => $command_name,
        'callback' => [$commandhandler, $commandinfo->getMethodName()],
        'options' => [],
        'arguments' => [],
        'description' => $commandinfo->getDescription(),
        'examples' => [], // $commandinfo->getUsages(),
        'aliases' => $commandinfo->getAliases(),
      ];
      $command += drush_command_defaults($command_name, $commandfile, $commandfile_path);
      // TODO: count how many arguments are missing defaults, and set 'required-arguments'
      $commands[] = $command;
    }
  }
  drush_set_context('DRUSH_ANNOTATED_COMMANDS', $commands);
}
