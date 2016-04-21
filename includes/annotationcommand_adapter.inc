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
  $annotation_commandfiles = drush_get_context('DRUSH_ANNOTATED_COMMANDFILES');
  // Remove any entry in the commandfiles list from an ignored module.
  $ignored = implode('|', drush_get_option_list('ignored-modules'));
  $regex = "#/(modules|themes|profiles)(/|/.*/)($ignored)/#";
  foreach ($annotation_commandfiles as $key => $path) {
    if (preg_match($regex, $path)) {
      unset($annotation_commandfiles[$key]);
    }
  }
  return annotationcommand_adapter_get_commands($annotation_commandfiles);
}

/**
 * Search for annotation commands at the provided search path.
 * @see _drush_find_commandfiles()
 */
function annotationcommand_adapter_discover($searchpath, $phase = false, $phase_max = false) {
  if (empty($searchpath)) {
    return;
  }
  $annotation_commandfiles = [];
  // Assemble a cid specific to the bootstrap phase and searchpaths.
  // Bump $cf_version when making a change to a dev version of Drush
  // that invalidates the commandfile cache.
  $cf_version = 1;
  $cid = drush_get_cid('annotationfiles-' . $phase, array(), array_merge($searchpath, array($cf_version)));
  $command_cache = drush_cache_get($cid);
  if (isset($command_cache->data)) {
    $annotation_commandfiles = $command_cache->data;
  }
  else {
    // Check to see if this is the Drush searchpath for instances where we are
    // NOT going to do a full bootstrap (e.g. when running a help command)
    if (($phase == DRUSH_BOOTSTRAP_DRUPAL_SITE) && ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $searchpath = annotationcommand_adapter_refine_searchpaths($searchpath);
    }
    $discovery = annotationcommand_adapter_get_discovery();
    $annotation_commandfiles = $discovery->discoverNamespaced($searchpath, '\Drupal');
    drush_cache_set($cid, $annotation_commandfiles);
  }
  drush_set_context(
    'DRUSH_ANNOTATED_COMMANDFILES',
    array_merge(
      drush_get_context('DRUSH_ANNOTATED_COMMANDFILES'),
      $annotation_commandfiles
    )
  );
}

function annotationcommand_adapter_get_commands($annotation_commandfiles) {
  $factory = new AnnotatedCommandFactory();
  $commands = [];
  // This will give us a list containing something akin to:
  //   'modules/default_content/src/CliTools/DefaultContentCommands.php' =>
  //   '\\Drupal\\default_content\\CliTools\\DefaultContentCommands',
  foreach ($annotation_commandfiles as $commandfile_path => $commandfile_class) {
    if (file_exists($commandfile_path)) {
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
          'add-options-to-arguments' => TRUE,
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
  }
  return $commands;
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
    $pattern = '/.*\.info$/';
    if (drush_drupal_major_version() > 7) {
      $pattern = '/.*\.info.yml$/';
    }
    $locations = drush_scan_directory($path, $pattern, ['.', '..'], false, $max_depth);

    // Search for any directory that might be a module or theme (contains
    // a *.info or a *.info.yml file)
    foreach ($locations as $key => $info) {
      $result[dirname($key)] = true;
    }
  }
  return array_keys($result);
}
