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
 * @see drush_get_commands()
 */
function annotationcommand_adapter_commands() {
  $commands = drush_get_context('DRUSH_ANNOTATED_COMMANDS');
  return $commands;
}

/**
 * Search for annotation commands at the provided search path.
 * @see _drush_find_commandfiles()
 */
function annotationcommand_adapter_discover($searchpath, $phase = false, $phase_max = false) {
  if (empty($searchpath)) {
    return;
  }
  // Check to see if this is the Drush searchpath for instances where we are
  // NOT going to do a full bootstrap (e.g. when running a help command)
  if (($phase == DRUSH_BOOTSTRAP_DRUPAL_SITE) && ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    $searchpath = annotationcommand_adapter_refine_searchpaths($searchpath);
  }
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
        'examples' => $commandinfo->getExampleUsages(),
        'aliases' => $commandinfo->getAliases(),
      ];
      $required_arguments = 0;
      foreach ($commandinfo->getArguments() as $arg => $default) {
        $command['arguments'][$arg] = $commandinfo->getArgumentDescription($arg);
        if (!isset($default)) {
          ++$required_arguments;
        }
      }
      $command['required-arguments'] = $required_arguments;
      foreach ($commandinfo->getOptions() as $option => $default) {
        $command['options'][$option] = $commandinfo->getOptionDescription($option);
      }
      $command += drush_command_defaults($command_name, $commandfile, $commandfile_path);
      $commands[$command_name] = $command;
    }
  }
  drush_set_context('DRUSH_ANNOTATED_COMMANDS', $commands);
}

/**
 * In some circumstances, Drush just does a deep search for any *.drush.inc
 * file, so that it can find all commands, in enabled and disabled modules alike,
 * for the purpose of displaying the help text for that command.
 */
function annotationcommand_adapter_refine_searchpaths($searchpath) {
  $result = [];
  foreach ($searchpath as $path) {
    $max_depth = TRUE;
    $locations = drush_scan_directory($path, '/.*\.info.yml$/', ['.', '..'], false, $max_depth);

    // Search for any directory that might be a module or theme (contains
    // a *.info or a *.info.yml file)
    foreach ($locations as $key => $info) {
      $result[dirname($key)] = true;
    }
  }
  return array_keys($result);
}
