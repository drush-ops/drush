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
use Symfony\Component\Console\Input\ArrayInput;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Command\DrushInputAdapter;
use Drush\Command\DrushOutputAdapter;
use Drush\Drush;
use Drush\SiteAlias\AliasManagerAdapterInjector;
use Drush\SiteAlias\ProcessManagerInjector;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Cache the command file discovery object.
 *
 * @return CommandFileDiscovery
 */
function annotationcommand_adapter_get_discovery() {
  static $discovery;
  if (!isset($discovery)) {
    $discovery = new CommandFileDiscovery();
    $discovery
      ->setIncludeFilesAtBase(false)
      ->setSearchDepth(3)
      ->ignoreNamespacePart('contrib', 'Commands')
      ->ignoreNamespacePart('custom', 'Commands')
      ->ignoreNamespacePart('src')
      ->setSearchLocations(['Commands'])
      ->setSearchPattern('#.*Commands.php$#');
  }
  return $discovery;
}

/**
 * Initialize and cache the command factory. Drush 9 uses dependency injection.
 *
 * @return AnnotatedCommandFactory
 */
function annotationcommand_adapter_get_factory() {
  static $factory;
  if (!isset($factory)) {
    $factory = new AnnotatedCommandFactory();
    $factory->commandProcessor()->hookManager()->add('annotatedcomand_adapter_backend_result', HookManager::EXTRACT_OUTPUT);
    $formatter = new FormatterManager();
    $formatter->addDefaultFormatters();
    $formatter->addDefaultSimplifiers();
    $factory->commandProcessor()->setFormatterManager($formatter);
  }
  return $factory;
}

/**
 * Fetch the command processor from the factory.
 *
 * @return AnnotatedCommandFactory
 */
function annotationcommand_adapter_get_processor() {
  $factory = annotationcommand_adapter_get_factory();
  return $factory->commandProcessor();
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
  $site_wide_commands = annotationcommand_adapter_get_commands($annotation_commandfiles);
  $module_service_commands = drush_get_context('DRUSH_MODULE_SERVICE_COMMANDS');
  $commands = array_merge($site_wide_commands, $module_service_commands);

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

/**
 * This function is set as the $command['callback'] for Symfony Console commands
 * e.g. those provided by Drupal 8 modules.  Note that Drush 9 calls these with
 * Symfony's Application::run() method, but Drush 8 continues to use the
 * legacy Drush command dispatcher for all commands.
 *
 * @return bolean false if command failed (expect drush_set_error was called in this case)
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
 * been converted to annotated commands.  Note that Drush 9 calls these with
 * Symfony's Application::run() method, but Drush 8 continues to use the
 * legacy Drush command dispatcher for all commands.
 *
 * @return bolean false if command failed (expect drush_set_error was called in this case)
 */
function annotationcommand_adapter_process_command() {
  $userArgs = func_get_args();
  $commandprocessor = annotationcommand_adapter_get_processor();
  $command = drush_get_command();
  annotationcommand_adapter_add_hook_options($command);
  $args = [];
  foreach ($command['consolidation-arg-defaults'] as $key => $default) {
    $value = array_shift($userArgs);
    if (!isset($value)) {
      $value = $default;
    }
    $args[$key] = $value;
  }

  $input = new DrushInputAdapter($args, annotationcommand_adapter_get_options($command), $command['command']);
  $output = new DrushOutputAdapter();
  annotationcommand_adapter_input($input);
  $annotationData = $command['annotations'];
  $commandData = new CommandData(
    $annotationData,
    $input,
    $output
  );
  $commandData->setIncludeOptionsInArgs($command['add-options-to-arguments']);
  $names = annotationcommand_adapter_command_names($command);

  // For now, the only thing that can be injected is the InputInterface.
  foreach ($command['injected-classes'] as $injected) {
    switch ($injected) {
      case 'Symfony\Component\Console\Input\InputInterface':
        $commandData->injectInstance($input);
        break;
    }
  }

  // n.b.: backend result is set by a post-alter hook.
  $result = $commandprocessor->process(
    $output,
    $names,
    $command['annotated-command-callback'],
    $commandData
  );

  return $result;
}

function annotationcommand_adapter_input($setInput = null) {
  static $cacheInput = null;
  if ($setInput != null) {
    $cacheInput = $setInput;
  }
  return $cacheInput;
}

/**
 * Internal function called by annotationcommand_adapter_commands, which
 * is called by drush_get_commands().
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
    try {
      if (file_exists($commandfile_path) && ($commandfile_class != '\Drush\Commands\DrushCommands')) {
        $commandhandler = annotationcommand_adapter_create_commandfile_instance($commandfile_path, $commandfile_class);
        $commands_for_this_commandhandler = annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path);
        $commands = array_merge($commands, $commands_for_this_commandhandler);
      }
    }
    catch (\Exception $e) {}
  }
  return $commands;
}

use Psr\Log\LoggerAwareInterface;


/**
 * Create and cache a commandfile instance.
 *
 * @param string $commandfile_path Path to the commandfile implementation
 * @param string $commandfile_class Namespace and class of the commandfile object
 *
 * @return object
 */
function annotationcommand_adapter_create_commandfile_instance($commandfile_path, $commandfile_class) {
  $cache =& drush_get_context('DRUSH_ANNOTATION_COMMANDFILE_INSTANCES');
  if (!isset($cache[$commandfile_path])) {
    include_once $commandfile_path;
    $commandhandler = new $commandfile_class;

    // Inject logger if requested
    if ($commandhandler instanceof LoggerAwareInterface) {
      $commandhandler->setLogger(Drush::logger());
    }

    if (class_exists('\Consolidation\SiteAlias\SiteAliasManager')) {
      $alias_manager_injector = new AliasManagerAdapterInjector();
      $alias_manager_injector->inflect($commandhandler);
    }

    if (class_exists('\Consolidation\SiteProcess\ProcessManager')) {
      $process_manager_injector = new ProcessManagerInjector();
      $process_manager_injector->inflect($commandhandler);
    }

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
  $commands = annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path, false);
  drush_set_context('DRUSH_MODULE_SERVICE_COMMANDS', array_merge($commands, $module_service_commands));
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

function annotationcommand_adapter_bootstrap_phase_index($phase)
{
  $phaseMap = annotationcommand_adapter_bootstrap_phase_map();
  if (isset($phaseMap[$phase])) {
    return $phaseMap[$phase];
  }

  if ((substr($phase, 0, 16) != 'DRUSH_BOOTSTRAP_') || (!defined($phase))) {
    return;
  }
  return constant($phase);
}

function annotationcommand_adapter_bootstrap_phase_map()
{
  return [
    'none' => DRUSH_BOOTSTRAP_NONE,
    'root' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'site' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'config' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'configuration' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'db' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
    'database' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
    'full' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  ];
}

/**
 * Convert an annotated command command handler object into a Drush $command record.
 *
 * @param object $commandhandler Command handler object
 * @param string $commandfile_path
 * @param boolean $includeAllPublicMethods TODO remove this, make it 'false' always
 *
 * @return array Drush $command record
 */
function annotationcommand_adapter_get_commands_for_commandhandler($commandhandler, $commandfile_path, $includeAllPublicMethods = true) {
  if (!$commandhandler) {
    return [];
  }
  $cache =& drush_get_context('DRUSH_ANNOTATION_COMMANDS_FOR_COMMANDFILE');
  if (isset($cache[$commandfile_path])) {
    return $cache[$commandfile_path];
  }
  $factory = annotationcommand_adapter_get_factory();
  $commands = [];
  $commandfile = basename($commandfile_path, '.php');

  $commandinfo_list = $factory->getCommandInfoListFromClass($commandhandler);

  foreach ($commandinfo_list as $commandinfo) {
    // Hooks are automatically registered when the commandhandler is
    // created via registerCommandClass(), so we don't need to do it again here.
    // $factory->registerCommandHook($commandinfo, $commandhandler);
    $aliases = $commandinfo->getAliases();
    $command_name = strtolower($commandinfo->getName());
    $standard_alias = str_replace(':', '-', $command_name);
    if ($command_name != $standard_alias) {
      $aliases[] = $standard_alias;
    }
    $handle_remote_commands = $commandinfo->getAnnotation('handle-remote-commands') == 'true';
    // TODO: if there is no 'bootstrap' annotation, maybe we should default to NONE instead of FULL?
    if ($bootstrap = $commandinfo->getAnnotation('bootstrap')) {
      // Convert from the bootstrap string to the appropriate bootstrap phase index
      $bootstrap = annotationcommand_adapter_bootstrap_phase_index($bootstrap);
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
      'handle-remote-commands' => $handle_remote_commands,
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
    $command['annotations'] = $commandinfo->getAnnotations();
    $command['injected-classes'] = $commandinfo->getInjectedClasses();
    // If the command has a '@return' annotation, then
    // remember information we will need to use the output formatter.
    $returnType = $commandinfo->getReturnType();
    if (isset($returnType)) {
      $command['return-type'] = $returnType;
    }
    $command += drush_command_defaults($command_name, $commandfile, $commandfile_path);
    $commands[$command_name] = $command;
  }
  $cache[$commandfile_path] = $commands;
  return $commands;
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
  $names[] = '*'; // we are missing annotations here; maybe we just don't support that? (TODO later, maybe)
  $factory = annotationcommand_adapter_get_factory();
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
  $factory = annotationcommand_adapter_get_factory();
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
    $hook($commandData->input(), $commandData->annotationData());
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
 *   current command invokation, including the InputInterface, OutputInterface
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
  $factory = annotationcommand_adapter_get_factory();
  $hookManager = $factory->hookManager();

  $hooks = $hookManager->getHooks($names, $hooks, $commandData->annotationData());

  foreach ((array)$hooks as $hook) {
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
  $factory = annotationcommand_adapter_get_factory();
  $hookManager = $factory->hookManager();

  $annotationData = $commandData->annotationData();
  $hooks = $hookManager->getHooks($names, $hooks, $annotationData);

  foreach ((array)$hooks as $hook) {
    $validated = $hook($commandData);
    // TODO: if $validated is a CommandError, maybe the best thing to do is 'return drush_set_error()'?
    if (is_object($validated) || ($validated === false)) {
      return $validated;
    }
  }
  return true;
}

/**
 * TODO: Document
 */
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

/**
 * TODO: Document
 */
function annotationcommand_adapter_alter_option_description_fields($option_help, $commandinfo, $default) {
  $formatOptions = new FormatterOptions($commandinfo->getAnnotations()->getArrayCopy());
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
