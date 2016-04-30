<?php

/**
 * @file
 * annotationcommand_adapter.inc
 */

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Consolidation\AnnotatedCommand\CommandProcessor;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\FormatterOptions;

use Symfony\Component\Console\Output\ConsoleOutput;

// We could use a DI container just to fix these
// static factory functions. (Presently, the master branch
// uses a DI container, but the 8.x branch does not.)

function annotationcommand_adapter_get_discovery() {
  static $discovery;
  if (!isset($discovery)) {
    $discovery = new CommandFileDiscovery();
  }
  return $discovery;
}

function annotationcommand_adapter_get_factory() {
  static $factory;
  if (!isset($factory)) {
    $factory = new AnnotatedCommandFactory();
  }
  return $factory;
}

function annotatedcomand_adapter_get_formatter() {
  static $formatter;
  if (!isset($formatter)) {
    $formatter = new FormatterManager();
  }
  return $formatter;
}

function annotatedcomand_adapter_backend_result($structured_data, $args)
{
  $return = drush_backend_get_result();
  if (empty($return)) {
    drush_backend_set_result($structured_output);
  }
}

function annotationcommand_adapter_get_processor() {
  static $processor;
  if (!isset($processor)) {
    $hook_manager = new HookManager();
    // AlterResultInterface
    $resultAlterer =
    $hook_manager->add('*', 'post-alter', 'annotatedcomand_adapter_backend_result');
    $processor = new CommandProcessor($hook_manager);
    $processor->setFormatterManager(annotatedcomand_adapter_get_formatter());
  }
  return $processor;
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

function annotationcommand_adapter_process_command() {
  $commandprocessor = annotationcommand_adapter_get_processor();
  $args = func_get_args();
  $command = drush_get_command();
  $default_options = $command['consolidation-option-defaults'];
  if (!empty($default_options)) {
    $command_options = array_pop($args);
    $args[] = $command_options + $default_options;
  }

  $output = new ConsoleOutput();
  $names = array_merge([$command['command']], $command['aliases']);

  // n.b.: backend result is set by a post-alter hook.
  $result = $commandprocessor->process(
    $output,
    $names,
    $command['annotated-command-callback'],
    $command['annotations'],
    $args
  );

  return $result;
}

function annotationcommand_adapter_get_commands($annotation_commandfiles) {
  $factory = annotationcommand_adapter_get_factory();
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
        $aliases = $commandinfo->getAliases();
        $command_name = strtolower($commandinfo->getName());
        $aliases[] = str_replace(':', '-', $command_name);
        $commandfile = ''; // what value should this take?
        $command = [
          'name' => $command_name,
          //'callback' => [$commandhandler, $commandinfo->getMethodName()],
          'callback' => 'annotationcommand_adapter_process_command',
          'annotated-command-callback' => [$commandhandler, $commandinfo->getMethodName()],
          'options' => [],
          'arguments' => [],
          'description' => $commandinfo->getDescription(),
          'examples' => $commandinfo->getExampleUsages(),
          'aliases' => $aliases,
          'add-options-to-arguments' => TRUE,
          'consolidation-output-formatters' => TRUE,
          'consolidation-option-defaults' => $commandinfo->options()->getValues(),
        ];
        $required_arguments = 0;
        foreach ($commandinfo->arguments()->getValues() as $arg => $default) {
          $command['arguments'][$arg] = $commandinfo->arguments()->getDescription($arg);
          if (!isset($default)) {
            ++$required_arguments;
          }
        }
        $command['required-arguments'] = $required_arguments;
        foreach ($commandinfo->options()->getValues() as $option => $default) {
          $description = $commandinfo->options()->getDescription($option);
          $command['options'][$option] = ['description' => $description];
          if (!empty($default)) {
            $command['options'][$option]['example-value'] = $default;
          }
          $fn = 'annotationcommand_adapter_alter_option_description_' . $option;
          if (function_exists($fn)) {
            $command['options'][$option] = $fn($command['options'][$option], $commandinfo, $default);
          }
        }
        // If the command has a '@return' annotation, then
        // remember information we will need to use the output formatter.
        $returnType = $commandinfo->getReturnType();
        if (isset($returnType)) {
          $command['annotations'] = $commandinfo->getAnnotations();
          $command['return-type'] = $returnType;
        }
        $command += drush_command_defaults($command_name, $commandfile, $commandfile_path);
        $commands[$command_name] = $command;
      }
    }
  }
  return $commands;
}

function annotationcommand_adapter_alter_option_description_format($option_help, $commandinfo, $default) {
  $formatterManager = annotatedcomand_adapter_get_formatter();
  $return_type = $commandinfo->getReturnType();
  if (!empty($return_type)) {
    $available_formats = $formatterManager->validFormats($return_type);
    $option_help['description'] = dt('Select output format. Available: !formats.', array('!formats' => implode(', ', $available_formats)));
    if (!empty($default)) {
      $option_help['description'] .= dt(' Default is !default.', array('!default' => $default));
    }
  }
  return $option_help;
}

function annotationcommand_adapter_alter_option_description_fields($option_help, $commandinfo, $default) {
  $formatOptions = new FormatterOptions($commandinfo->getAnnotations());
  $field_labels = $formatOptions->get(FormatterOptions::FIELD_LABELS);
  $default_fields = $formatOptions->get(FormatterOptions::DEFAULT_FIELDS, [], array_keys($field_labels));
  $available_fields = array_keys($field_labels);
  $option_help['example-value'] = implode(', ', $default_fields);
  $option_help['description'] = dt('Fields to output. All available fields are: !available.', array('!available' => implode(', ', $available_fields)));
  return $option_help;
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
