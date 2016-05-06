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
use Symfony\Component\Console\Input\ArrayInput;

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

function annotatedcomand_adapter_backend_result($structured_data) {
  $return = drush_backend_get_result();
  if (empty($return)) {
    drush_backend_set_result($structured_data);
  }
}

function annotationcommand_adapter_get_processor() {
  static $processor;
  if (!isset($processor)) {
    $hook_manager = new HookManager();
    // AlterResultInterface
    $resultAlterer =
    $hook_manager->add('annotatedcomand_adapter_backend_result', HookManager::EXTRACT_OUTPUT);
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
  $commands = annotationcommand_adapter_get_commands($annotation_commandfiles);
  $module_service_commands = drush_get_context('DRUSH_MODULE_SERVICE_COMMANDS');
  return array_merge($commands, $module_service_commands);
}

/**
 * Search for annotation commands at the provided search path.
 * @see _drush_find_commandfiles()
 */
function annotationcommand_adapter_discover($searchpath, $phase = false, $phase_max = false) {
  if (empty($searchpath)) {
    return;
  }
  if (($phase >= DRUSH_BOOTSTRAP_DRUPAL_SITE) && (drush_drupal_major_version() >= 8)) {
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

function annotationcommand_adapter_run_console_command() {
  $args = func_get_args();
  $command = drush_get_command();

  $console_command = $command['drush-console-command'];
  // TODO: Build an appropriate input object
  $input = annotationcommand_adapter_build_input($console_command, $args);
  $output = new ConsoleOutput();
  $result = $console_command->run($input, $output);

  return $result;
}

function annotationcommand_adapter_build_input($console_command, $userArgs) {
  $args = [];
  $defaultOptions = [];
  $definition = $console_command->getDefinition();
  $inputArguments = $definition->getArguments();
  foreach ($inputArguments as $key => $inputOption) {
    $value = array_shift($userArgs);
    if (!isset($value)) {
      $value = $inputOption->getDefault();
    }
    $args[$key] = $value;
  }
  $inputOptions = $definition->getOptions();
  foreach ($inputOptions as $option => $inputOption) {
    $defaultOptions[$option] = $inputOption->getDefault();
  }
  foreach ($defaultOptions as $option => $value) {
    $args["--$option"] = drush_get_option($option, $value);
  }
  // TODO: Need to add global options. Note that ArrayInput is validated.
  $input = new ArrayInput($args, $definition);
  return $input;
}

function annotationcommand_adapter_process_command() {
  $userArgs = func_get_args();
  $commandprocessor = annotationcommand_adapter_get_processor();
  $command = drush_get_command();
  $args = [];
  foreach ($command['consolidation-arg-defaults'] as $key => $default) {
    $value = array_shift($userArgs);
    if (!isset($value)) {
      $value = $default;
    }
    $args[$key] = $value;
  }
  $default_options = $command['consolidation-option-defaults'];
  $options = drush_redispatch_get_options() + $default_options;
  $args[] = $options;

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
  $commands = [];
  // This will give us a list containing something akin to:
  //   'modules/default_content/src/CliTools/DefaultContentCommands.php' =>
  //   '\\Drupal\\default_content\\CliTools\\DefaultContentCommands',
  foreach ($annotation_commandfiles as $commandfile_path => $commandfile_class) {
    if (file_exists($commandfile_path)) {
      include_once $commandfile_path;
      $commandhandler = new $commandfile_class;
      $commands_for_this_commandhandler = annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path);
      $commands = array_merge($commands, $commands_for_this_commandhandler);
    }
  }
  return $commands;
}

function annotationcommand_adapter_cache_module_console_commands($console_command, $commandfile_path = null) {
  if (!isset($commandfile_path)) {
    $class = new \ReflectionClass($console_command);
    $commandfile_path = $class->getFileName();
  }
  $module_service_commands = drush_get_context('DRUSH_MODULE_SERVICE_COMMANDS');
  $commands = annotationcommand_adapter_get_command_for_console_command($console_command, $commandfile_path);
  drush_set_context('DRUSH_MODULE_SERVICE_COMMANDS', array_merge($commands, $module_service_commands));
}

function annotationcommand_adapter_cache_module_service_commands($commandhandler, $commandfile_path = null) {
  if (!isset($commandfile_path)) {
    $class = new \ReflectionClass($commandhandler);
    $commandfile_path = $class->getFileName();
  }
  $module_service_commands = drush_get_context('DRUSH_MODULE_SERVICE_COMMANDS');
  $commands = annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path, false);
  drush_set_context('DRUSH_MODULE_SERVICE_COMMANDS', array_merge($commands, $module_service_commands));
}

function annotationcommand_adapter_get_command_for_console_command($console_command, $commandfile_path) {
  $commands = [];
  $commandfile = basename($commandfile_path, '.php');
  $factory = annotationcommand_adapter_get_factory();
  $inputDefinition = $console_command->getDefinition();
  $inputArguments = $inputDefinition->getArguments();
  $inputOptions = $inputDefinition->getOptions();
  $aliases = $console_command->getAliases();
  $command_name = strtolower($console_command->getName());
  $standard_alias = str_replace(':', '-', $command_name);
  if ($command_name != $standard_alias) {
    $aliases[] = $standard_alias;
  }
  $command = [
    'name' => $command_name,
    'callback' => 'annotationcommand_adapter_run_console_command',
    'drush-console-command' => $console_command,
    'commandfile' => $commandfile,
    'category' => $commandfile,
    'options' => [],
    'arguments' => [],
    'description' => $console_command->getDescription(),
    'examples' => $console_command->getUsages(),
    'aliases' => $aliases,
  ];
  foreach ($inputArguments as $arg => $inputArg) {
    $command['arguments'][$arg] = $inputArg->getDescription();
  }
  $command['required-arguments'] = $inputDefinition->getArgumentRequiredCount();
  foreach ($inputOptions as $option => $inputOption) {
    $description = $inputOption->getDescription();
    $default = $inputOption->getDefault();
    $command['options'][$option] = ['description' => $description];
    if (!empty($default)) {
      $command['options'][$option]['example-value'] = $default;
    }
  }
  $command += drush_command_defaults($command_name, $commandfile, $commandfile_path);
  $commands[$command_name] = $command;
  return $commands;
}

function annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path, $includeAllPublicMethods = true) {
  $factory = annotationcommand_adapter_get_factory();
  $commands = [];
  $commandfile = basename($commandfile_path, '.php');

  $commandinfo_list = $factory->getCommandInfoListFromClass($commandhandler, $includeAllPublicMethods);

  foreach ($commandinfo_list as $commandinfo) {
    $aliases = $commandinfo->getAliases();
    $command_name = strtolower($commandinfo->getName());
    $standard_alias = str_replace(':', '-', $command_name);
    if ($command_name != $standard_alias) {
      $aliases[] = $standard_alias;
    }
    $command = [
      'name' => $command_name,
      //'callback' => [$commandhandler, $commandinfo->getMethodName()],
      'callback' => 'annotationcommand_adapter_process_command',
      'annotated-command-callback' => [$commandhandler, $commandinfo->getMethodName()],
      'commandfile' => $commandfile,
      'category' => $commandfile,
      'options' => [],
      'arguments' => [],
      'description' => $commandinfo->getDescription(),
      'examples' => $commandinfo->getExampleUsages(),
      'aliases' => $aliases,
      'add-options-to-arguments' => TRUE,
      'consolidation-output-formatters' => TRUE,
      'consolidation-option-defaults' => $commandinfo->options()->getValues(),
      'consolidation-arg-defaults' => $commandinfo->arguments()->getValues(),
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
