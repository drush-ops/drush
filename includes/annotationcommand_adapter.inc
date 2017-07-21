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
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\ArrayInput;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Command\DrushInputAdapter;
use Drush\Command\DrushOutputAdapter;
use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Cache the command file discovery object.
 * @todo There probably isn't any need to cache this object.
 *
 * @return CommandFileDiscovery
 */
function annotationcommand_adapter_get_discovery() {
  static $discovery;
  if (!isset($discovery)) {
    $discovery = new CommandFileDiscovery();
    $discovery
      ->setIncludeFilesAtBase(false)
      ->setSearchLocations(['Commands'])
      ->setSearchPattern('#.*Commands.php$#');
  }
  return $discovery;
}

/**
 * Fetch the command processor from the factory.
 *
 * @return AnnotatedCommandFactory
 */
function annotationcommand_adapter_get_processor() {
  $factory = Drush::commandFactory();
  return $factory->commandProcessor()->setPassExceptions(true);
}

/**
 * Fetch the formatter manager from the command processor
 *
 * @return FormatterManager
 */
function annotatedcomand_adapter_get_formatter() {
  $commandProcessor = annotationcommand_adapter_get_processor();
  return $commandProcessor->formatterManager();
}

/**
 * Callback function called by HookManager::EXTRACT_OUTPUT to set
 * the backend result.
 */
function annotatedcomand_adapter_backend_result($structured_data) {
  $return = drush_backend_get_result();
  if (empty($return)) {
    drush_backend_set_result($structured_data);
  }
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
    // These commands are already discovered by \Drush\Boot\DrupalBoot8::bootstrapDrupalFull
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

/**
 * This function is set as the $command['callback'] for Symfony Console commands
 * e.g. those provided by Drupal 8 modules.  When the DRUSH_SYMFONY environment
 * variable is set, these will be called via Symfony's Application::run() method.
 * Otherwise, the legacy Drush command dispatcher will be used for all commands.
 *
 * @return bool false if command failed (expect drush_set_error was called in this case)
 */
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

/**
 * TODO: This could probably just be a DrushInputAdapter now.
 */
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

/**
 * Collect all of the options defined in every relevant context, and
 * merge them together to form the options array.
 *
 * @return array
 */
function annotationcommand_adapter_get_options($command) {
  $default_options = isset($command['consolidation-option-defaults']) ? $command['consolidation-option-defaults'] : [];
  $options = drush_redispatch_get_options() + $default_options;

  $options += drush_get_merged_options();

  return $options;
}

/**
 * This function is set as the $command['callback'] for commands that have
 * been converted to annotated commands.  When the DRUSH_SYMFONY environment
 * variable is set, these will be called via Symfony's Application::run() method.
 * Otherwise, the legacy Drush command dispatcher will be used for all commands.
 *
 * @return boolean false if command failed (expect drush_set_error was called in this case)
 */
function annotationcommand_adapter_process_command() {
  $userArgs = func_get_args();
  $commandprocessor = annotationcommand_adapter_get_processor();
  $command = drush_get_command();
  annotationcommand_adapter_add_hook_options($command);

  $args = annotationcommand_adapter_process_args($userArgs, $command['consolidation-arg-defaults']);

  // TODO: Need to determine if $input is interactive, and ensure that $input->isInteractive() returns the correct result.
  $input = new DrushInputAdapter($args, annotationcommand_adapter_get_options($command), $command['command']);
  $output = new ConsoleOutput();
  $annotationData = $command['annotations'];
  $commandData = new CommandData(
    $annotationData,
    $input,
    $output
  );
  $commandData->setIncludeOptionsInArgs($command['add-options-to-arguments']);
  $names = annotationcommand_adapter_command_names($command);

  // Run any `@hook init` for this command. c.f. AnnotatedCommand::initialize()
  $commandprocessor->initializeHook($input, $names, $annotationData);

  // Run any `@hook interact` for this command. c.f. AnnotatedCommand::interact()
  if ($input->isInteractive()) {
    $commandprocessor->interact($input, $output, $names, $annotationData);
  }

  // Run any validate, command, process, alter or handle results hooks. c.f. AnnotatedCommand::execute()
  // n.b.: backend result is set by a global post-alter hook.
  $result = null;
  try {
    $result = $commandprocessor->process(
      $output,
      $names,
      $command['annotated-command-callback'],
      $commandData
    );
  }
  catch (\Exception $e) {
    drush_set_error($e->getMessage());
  }

  return $result;
}

function annotationcommand_adapter_process_args($userArgs, $defaults) {
  $args = [];
  foreach ($defaults as $key => $default) {
    if (is_array($default)) {
      $args[$key] = $userArgs;
      return $args;
    }
    $value = array_shift($userArgs);
    if (!isset($value)) {
      $value = $default;
    }
    $args[$key] = $value;
  }
  return $args;
}

/**
 * Called by drush_get_commands().
 *
 * @param array $annotation_commandfiles path => class mapping
 *
 * @return object[]
 */
function annotationcommand_adapter_get_commands($annotation_commandfiles) {
  $commands = [];
  // This will give us a list containing something akin to:
  //   'modules/default_content/src/CliTools/DefaultContentCommands.php' =>
  //   '\\Drupal\\default_content\\CliTools\\DefaultContentCommands',
  foreach ($annotation_commandfiles as $commandfile_path => $commandfile_class) {
    if (file_exists($commandfile_path)) {
      $commandhandler = annotationcommand_adapter_create_commandfile_instance($commandfile_path, $commandfile_class);
      $commands_for_this_commandhandler = annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path);
      $commands = array_merge($commands, $commands_for_this_commandhandler);
    }
  }
  return $commands;
}

/**
 * Create and cache a commandfile instance.
 *
 * @param string $commandfile_path Path to the commandfile implementation
 * @param string $commandfile_class Namespace and class of the commandfile object
 *
 * @return object
 */
function annotationcommand_adapter_create_commandfile_instance($commandfile_path, $commandfile_class) {
  $runner = Drush::runner();
  $app = Drush::service('application');
  $cache =& drush_get_context('DRUSH_ANNOTATION_COMMANDFILE_INSTANCES');
  if (!isset($cache[$commandfile_path])) {
    include_once $commandfile_path;
    $commandhandler = $runner->registerCommandClass($app, $commandfile_class);
    $cache[$commandfile_path] = $commandhandler;
  }
  return $cache[$commandfile_path];
}

/**
 * TODO: document
 */
function annotationcommand_adapter_cache_module_console_commands($console_command, $commandfile_path = null) {
  if (!isset($commandfile_path)) {
    $class = new \ReflectionClass($console_command);
    $commandfile_path = $class->getFileName();
  }
  $module_service_commands = drush_get_context('DRUSH_MODULE_SERVICE_COMMANDS');
  $commands = annotationcommand_adapter_get_command_for_console_command($console_command, $commandfile_path);
  drush_set_context('DRUSH_MODULE_SERVICE_COMMANDS', array_merge($commands, $module_service_commands));
  // Register these commands in the Application.
  annotationcommand_adapter_create_commandfile_instance($commandfile_path, $class->getName());
}

/**
 * TODO: document
 */
function annotationcommand_adapter_cache_module_service_commands($commandhandler, $commandfile_path = null) {
  if (!isset($commandfile_path)) {
    $class = new \ReflectionClass($commandhandler);
    $commandfile_path = $class->getFileName();
  }
  $module_service_commands = drush_get_context('DRUSH_MODULE_SERVICE_COMMANDS');
  $commands = annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path);
  drush_set_context('DRUSH_MODULE_SERVICE_COMMANDS', array_merge($commands, $module_service_commands));
  // Register these commands in the Application.
  annotationcommand_adapter_create_commandfile_instance($commandfile_path, $commandhandler);
}

/**
 * Convert a Symfony Console command into a Drush $command record
 *
 * @param Symfony\Component\Console\Command\Command $console_command The Symfony Console command to convert
 * @param string $commandfile_path Path to console command file
 *
 * @return array Drush $command record
 */
function annotationcommand_adapter_get_command_for_console_command($console_command, $commandfile_path) {
  $commands = [];
  $commandfile = basename($commandfile_path, '.php');
  $factory = Drush::commandFactory();
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

/**
 * Convert an annotated command command handler object into a Drush $command record.
 *
 * @param object $commandhandler Command handler object
 * @param string $commandfile_path
 *
 * @return array Drush $command record
 */
function annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path) {
  $cache =& drush_get_context('DRUSH_ANNOTATION_COMMANDS_FOR_COMMANDFILE');
  if (isset($cache[$commandfile_path])) {
    return $cache[$commandfile_path];
  }
  $factory = Drush::commandFactory();
  $commands = [];
  $commandfile = basename($commandfile_path, '.php');

  $commandinfo_list = $factory->getCommandInfoListFromClass($commandhandler);

  foreach ($commandinfo_list as $commandinfo) {
    // Hooks are automatically registered when the commandhandler is
    // created via registerCommandClass(), so we don't need to do it again here.
    // $factory->registerCommandHook($commandinfo, $commandhandler);
    // Skip anything that is not a command
    if (!AnnotatedCommandFactory::isCommandMethod($commandinfo, false)) {
      continue;
    }

    $aliases = $commandinfo->getAliases();
    $command_name = strtolower($commandinfo->getName());
    $standard_alias = str_replace(':', '-', $command_name);
    if ($command_name != $standard_alias) {
      $aliases[] = $standard_alias;
    }
    // If there is no 'bootstrap' annotation, default to NONE.
    $bootstrap = DRUSH_BOOTSTRAP_NONE;
    if ($bootstrap = $commandinfo->getAnnotation('bootstrap')) {
      $bootstrap = constant($bootstrap);
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
      'bootstrap' => $bootstrap,
      'remote-tty' => $commandinfo->hasAnnotation('remote-tty'),
      'handle-remote-commands' => $commandinfo->hasAnnotation('handle-remote-commands'),
      'strict-option-handling' => $commandinfo->hasAnnotation('strict-option-handling'),
      'hidden' => $commandinfo->hasAnnotation('hidden'),
      'aliases' => $aliases,
      'topic' => $commandinfo->hasAnnotation('topic'),
      'topics' => $commandinfo->getTopics(),
      'add-options-to-arguments' => TRUE,
      'consolidation-output-formatters' => TRUE,
      'consolidation-option-defaults' => $commandinfo->options()->getValues(),
      'consolidation-arg-defaults' => $commandinfo->arguments()->getValues(),
    ];
    $required_arguments = 0;
    foreach ($commandinfo->arguments()->getValues() as $arg => $default) {
      $command['arguments'][$arg] = $commandinfo->arguments()->getDescription($arg);
      if (!$commandinfo->arguments()->hasDefault($arg)) {
        ++$required_arguments;
      }
    }
    // $command['required-arguments'] = $required_arguments;

    // We just need to declare the option. The values are no longer used.
    foreach ($commandinfo->options()->getValues() as $option => $default) {
      $command['options'][$option] = '';
    }
    $command['annotations'] = $commandinfo->getAnnotations();
    // If the command has a '@return' annotation, then
    // remember information we will need to use the output formatter.
    $returnType = $commandinfo->getReturnType();
    if (isset($returnType)) {
      $command['return-type'] = $returnType;
    }
    if ($additional = $commandinfo->getAnnotation('allow-additional-options')) {
      $command['allow-additional-options'] = StringUtils::csvToArray($additional);
    }
    if ($dependencies = $commandinfo->getAnnotation('drupal-dependencies')) {
      $command['drupal dependencies'] = StringUtils::csvToArray($dependencies);
    }
    $command += drush_command_defaults($command_name, $commandfile, $commandfile_path);
    $commands[$command_name] = $command;
  }
  $cache[$commandfile_path] = $commands;
  return $commands;
}

/**
 * Determine whether command record represents an Annotated command.
 *
 * @param array $command
 *
 * @return bool
 */
function annotation_adapter_is_annotated(array $command) {
  return !empty($command['annotated-command-callback']);
}

/**
 * Add legacy commands to the $application. Used by help and list commands.
 *
 * @deprecated Will be removed once dispatch pipeline is modernized.
 * @param $application
 */
function annotation_adapter_add_legacy_commands_to_application($application) {
  $commands = drush_get_commands();
  foreach ($commands as $command) {
    if (!annotation_adapter_is_annotated($command) && empty($command['hidden'])) {
      $annotated = new AnnotatedCommand($command['command']);
      $annotated->setDescription($command['description']);
      $annotated->setAliases($command['aliases']);
      foreach ($command['arguments'] as $name => $description) {
        $annotated->addArgument($name, InputArgument::OPTIONAL, $description);
      }
      foreach ($command['options'] as $name => $option) {
        if (!is_array($option)) {
          $option = ['description' => $option];
        }
        if (!array_key_exists('default_value', $option)) {
          $option['default_value'] = '';
        }
        $annotated->addOption($name, NULL, $option['default_value'] ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED, $option['description'], $option['default_value']);
      }
      foreach ($command['examples'] as $name => $description) {
        // @todo
        // $annotated->addUsageOrExample($name, $description);
      }
//      if ($command['topics']) {
//        $annotated->setTopics($command['topics']);
//      }
      $annotated->setAnnotationData(new AnnotationData());
      $application->add($annotated);
    }
  }
}

/**
 * Modify a $command record, adding option definitions defined by any
 * command hook.
 *
 * @param array $command Drush command record to modify
 */
function annotationcommand_adapter_add_hook_options(&$command)
{
  // Get options added by hooks.  We postpone doing this until the
  // last minute rather than doing it when processing commandfiles
  // so that we do not need to worry about what order we process the
  // commandfiles in -- we can load extensions late, and still have
  // the extension hook a core command, or have an early-loaded global
  // extension hook a late-loaded extension (e.g. attached to a module).
  $names = annotationcommand_adapter_command_names($command);
  $names[] = '*';
  // Add possible hooks.
  $blacklist = ['command', 'handle-remote-commands', 'strict-option-handling', 'allow-additional-options', 'bootstrap', 'topic', 'hidden', 'hidden-options'];
  /** @var \Consolidation\AnnotatedCommand\AnnotationData $annotationData */
  if ($annotationData = $command['annotations']) {
    $keys = array_diff($annotationData->keys(), $blacklist);
    $atkeys = [];
    foreach ($keys as $key) {
      $atkeys[] = '@'. $key;
    }
    $names = array_merge($names, $atkeys);
  }
  $factory = Drush::commandFactory();
  $extraOptions = $factory->hookManager()->getHookOptions($names);
  foreach ($extraOptions as $commandinfo) {
    if (!isset($command['consolidation-option-defaults'])) {
      $command['consolidation-option-defaults'] = array();
    }
    $command['consolidation-option-defaults'] += $commandinfo->options()->getValues();
    foreach ($commandinfo->options()->getValues() as $option => $default) {
      $description = $commandinfo->options()->getDescription($option);
      $command['options'][$option] = ['description' => $description];
      if (!empty($default)) {
        $command['options'][$option]['example-value'] = $default;
      }
      if (in_array($option, _convert_csv_to_array($commandinfo->getAnnotation('hidden-options')))) {
        $command['options'][$option]['hidden'] = TRUE;
      }
      $fn = 'annotationcommand_adapter_alter_option_description_' . $option;
      if (function_exists($fn)) {
        $command['options'][$option] = $fn($command['options'][$option], $commandinfo, $default);
      }
    }
  }
}

/**
 * Build all of the name variants for a Drush $command record
 *
 * @param array $command Drush command record
 *
 * @return string[]
 */
function annotationcommand_adapter_command_names($command)
{
  $names = array_merge(
    [$command['command']],
    $command['aliases']
  );
  if (!empty($command['annotated-command-callback'])) {
    $commandHandler = $command['annotated-command-callback'][0];
    $reflectionClass = new \ReflectionClass($commandHandler);
    $commandFileClass = $reflectionClass->getName();
    $names[] = $commandFileClass;
  }
  return $names;
}

/**
 * Convert from an old-style Drush initialize hook into annotated-command hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 */
function annotationcommand_adapter_call_initialize($names, CommandData $commandData)
{
  $factory = Drush::commandFactory();
  $hookManager = $factory->hookManager();

  $hooks = $hookManager->getHooks(
    $names,
    [
      HookManager::PRE_INITIALIZE,
      HookManager::INITIALIZE,
      HookManager::POST_INITIALIZE,
    ],
    $commandData->annotationData()
  );

  foreach ((array)$hooks as $hook) {
    if (!is_object($hook)) {
      $hook($commandData->input(), $commandData->annotationData());
    }
  }
}

/**
 * Convert from an old-style Drush pre-validate hook into annotated-command hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 *
 * @return boolean|object
 */
function annotationcommand_adapter_call_hook_pre_validate($names, CommandData $commandData)
{
  return annotationcommand_adapter_call_validate_interface(
    $names,
    [
      HookManager::PRE_ARGUMENT_VALIDATOR,
    ],
    $commandData
  );
}

/**
 * Convert from an old-style Drush validate hook into annotated-command hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 *
 * @return boolean|object
 */
function annotationcommand_adapter_call_hook_validate($names, CommandData $commandData)
{
  return annotationcommand_adapter_call_validate_interface(
    $names,
    [
      HookManager::ARGUMENT_VALIDATOR,
    ],
    $commandData
  );
}

/**
 * Convert from an old-style Drush pre-command hook into annotated-command hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 *
 * @return boolean|object
 */
function annotationcommand_adapter_call_hook_pre_command($names, CommandData $commandData)
{
  return annotationcommand_adapter_call_validate_interface(
    $names,
    [
      HookManager::PRE_COMMAND_HOOK,
    ],
    $commandData
  );
}

/**
 * Convert from an old-style Drush 'command' hook into annotated-command hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 *
 * @return boolean|object
 */
function annotationcommand_adapter_call_hook_command($names, CommandData $commandData)
{
  return annotationcommand_adapter_call_validate_interface(
    $names,
    [
      HookManager::COMMAND_HOOK,
    ],
    $commandData
  );
}

/**
 * Convert from an old-style Drush post-command hook into annotated-command hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invocation, including the InputInterface, OutputInterface
 *   and AnnotationData
 * @param mixed $return The return value of the command being executed
 *
 * @return mixed The altered command return value
 */
function annotationcommand_adapter_call_hook_post_command($names, CommandData $commandData, $return)
{
  return annotationcommand_adapter_call_process_interface(
    $names,
    [
      HookManager::POST_COMMAND_HOOK,
    ],
    $commandData,
    $return
  );
}

/**
 * After the primary Drush command hook is called, call all of the annotated-command
 * process and alter hooks.
 * @see _drush_invoke_hooks().
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 * @param mixed $return The return value of the command being executed
 *
 * @return mixed The altered command return value
 */
function annotationcommand_adapter_call_hook_process_and_alter($names, $commandData, $return)
{
  return annotationcommand_adapter_call_process_interface(
    $names,
    [
      HookManager::PRE_PROCESS_RESULT,
      HookManager::PROCESS_RESULT,
      HookManager::POST_PROCESS_RESULT,
      HookManager::PRE_ALTER_RESULT,
      HookManager::ALTER_RESULT,
      HookManager::POST_ALTER_RESULT,
    ],
    $commandData,
    $return
  );
}

/**
 * Given a list of hooks that conform to the interface ProcessResultInterface,
 * call them and return the result.
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param string[] $hooks All of the HookManager hooks that should be called
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 * @param mixed $return The return value of the command being executed
 *
 * @return mixed The altered command return value
 */
function annotationcommand_adapter_call_process_interface($names, $hooks, CommandData $commandData, $return)
{
  $factory = Drush::commandFactory();
  $hookManager = $factory->hookManager();

  $hooks = $hookManager->getHooks($names, $hooks, $commandData->annotationData());

  foreach ((array)$hooks as $hook) {
    // @todo: $hook might be a ProcessResultInterface object. Support those?
    if (is_object($hook)) {
      continue;
    }
    $result = $hook($return, $commandData);
    if (isset($result)) {
      $return = $result;
    }
  }
  return $return;
}

/**
 * Given a list of hooks that conform to the interface ValidatorInterface,
 * call them and return the result.
 *
 * @param string[] $names All of the applicable names for the command being hooked
 * @param string[] $hooks All of the HookManager hooks that should be called
 * @param CommandData $commandData All of the parameter data associated with the
 *   current command invokation, including the InputInterface, OutputInterface
 *   and AnnotationData
 *
 * @return boolean|object
 */
function annotationcommand_adapter_call_validate_interface($names, $hooks, CommandData $commandData)
{
  $factory = Drush::commandFactory();
  $hookManager = $factory->hookManager();
  $annotationData = $commandData->annotationData();

  $hooks = $hookManager->getHooks($names, $hooks, $annotationData);

  foreach ((array)$hooks as $hook) {
    // @todo: $hook might be a ValidatorInterface object. Support those?
    if (is_object($hook)) {
      continue;
    }
    $validated = $hook($commandData);
    // TODO: if $validated is a CommandError, maybe the best thing to do is 'return drush_set_error()'?
    if (is_object($validated) || ($validated === false)) {
      return $validated;
    }
  }
  return true;
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
