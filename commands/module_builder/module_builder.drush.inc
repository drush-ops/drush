<?php

/**
 * @file
 *  Module builder drush commands.
 */

/**
 * Implementation of hook_drush_init().
 *
 * Include common code.
 */
function module_builder_drush_init() {
  $command_info = drush_get_command();

  if ($command_info['commandfile'] == 'module_builder') {
    module_builder_drush_init_helper();
  }
}

/**
 * Load the file for the factory class and set the environment on it.
 *
 * Helper for hook_drush_init() and completion callbacks.
 */
function module_builder_drush_init_helper() {
  // Set up the MB factory.
  \DrupalCodeBuilder\Factory::setEnvironmentClass('Drush', drush_drupal_version());
}

/**
 * Implementation of hook_drush_command().
 */
function module_builder_drush_command() {
  $items = array();

  $items['mb-build'] = array(
    'callback' => 'drush_module_builder_callback_build_module',
    'description' => "Generate the code for a new Drupal module, including file headers and hook implementations.",
    'arguments' => array(
      'module name' => "The machine name of the module. Use '.' to specify the current folder name.",
      'hooks' => "Short names of hooks, separated by spaces.",
      'presets:' => "Names of preset groups of hooks, e.g., 'block'." . "\n" .
        "Use the 'presets:' marker to separate this from prior commands, eg 'mymodule hook_a hook_b presets: block'.",
      'plugins:' => "Plugin types and names concatenated with a '-'. The plugin type is the suffix of the plugin manager service ID, e.g., 'block'." . "\n" .
        "Use the 'plugins:' marker to separate this from prior commands. Example: 'mymodule hook_a hook_b plugins: block-alpha block-beta'.",
      'routes:' => "Menu paths, separated by spaces. " . "\n" .
        "Use the 'routes:' marker to separate this from prior commands, eg 'mymodule hook_a hook_b routes: module/path module/otherpath'.",
      'perms:' => "Permission names, separated by spaces. Optionally, the description can be appended with a '-' in between." . "\n" .
        "Use the 'perms:' marker to separate this from prior commands, eg 'mymodule hook_a hook_b perms: 'administer my module' 'access my module-Access my Module description'. Quote strings that contain spaces.",
      'theme:' => "Theme hooks, without the initial 'theme_', separated by spaces. " . "\n" .
        "Use the 'theme:' marker to separate this from prior commands, eg 'mymodule hook_a hook_b perms: my_themable'.",
      'services:' => "IDs of services, separated by spaces. " . "\n",
      'settings_form!' => "Add this argument to add a settings form to the module.",
      'api!' => "Add this argument to add an api.php file to the module.",
      'readme!' => "Add this argument to add a README file to the module.",
      'tests!' => "Add this argument to add a Simpletest test case file to the module.",
    ),
    // Commented out, as only the first argument is required.
    // TODO: figure out how to specify this!
    //'required-arguments' => TRUE,
    'aliases' => array('mb'),
    'options' => array(
      'noi' => "Disables interactive mode.",
      'data' => "Location to read hook data. May be absolute, or relative to Drupal files dir. Defaults to 'files/hooks'.",
      'build' => "Which module components to generate:
  - 'info' makes the info file.
  - 'readme' makes README file.
  - 'tests' makes the tests folder and test case file.
  - 'module', 'install' make the foo.module or foo.install file respectively.
  - 'FILE': If custom modules define other files to output, you can request those too, omitting the module root name part and any .inc extension, eg 'views' for 'foo.views.inc.
  - 'code' generates code files as needed: the module and install files, and any files requested by hooks.
  - 'all' generates everything, including any code files needed by the requested hooks.
Default is 'all' if writing new files, 'code' if appending to file or outputting only to terminal.",
      'write' => 'Write files to sites/all/modules. Will prompt to overwrite existing files; use yes to force. Use quiet to suppress output to the terminal.',
      'go' => 'Write all module files and enable the new module. Take two commands into the shower? Not me.',
      'add' => "Append hooks to module file. Implies 'write build=code'. Warning: will not check hooks already exist.",
      'name' => 'Readable name of the module.',
      'desc' => 'Description (for the admin module list).',
      'helptext' => 'Module help text (for the system help).',
      'dep' => 'Dependencies, separated by spaces, eg "forum views".',
      'package' => 'Module package.',
      'parent' => "Name of a module folder to place this new module into; use if this module is to be added to an existing package. Use '.' for the current working directory.",
    ),
    'examples' => array(
      'drush mb my_module menu cron nodeapi' =>
        'Generate module code with hook_menu, hook_cron, hook_nodeapi.',
      'drush mb my_module --build=info --name="My module" --dep="forum views"' =>
        'Generate module info with readable name and dependencies.',
      'drush mb my_module menu cron --write --name="My module" --dep="forum views"' =>
        'Generate both module files, write files and also output to terminal.',
      'drush mb my_module menu cron --write ' =>
        'Generate module code, write files and also output to terminal.',
      'drush mb my_module menu cron --write --quiet --name="My module" --dep="forum views"' =>
        'Generate both module files, write files and output nothing to terminal.',
      'drush mb my_module menu cron --add'=>
        'Generate code for hook_cron and add it to the existing my_module.module file.',
      'drush mb my_module menu cron --write --parent=cck'=>
        'Generate both module files, write files to a folder my_module inside the cck folder.',
      'drush mb my_module menu cron --write --parent=.'=>
        'Generate both module files, write files to a folder my_module in the current working directory.',
    ),
  );

  $items['mb-component'] = array(
    'callback' => 'drush_module_builder_callback_build_component',
    'aliases' => array('mbc'),
    'description' => "Generate a Drupal component, such as a module or profile.",
    'arguments' => array(
      'component type' => "The type of component, e.g., 'module'.",
    ),
    'options' => array(
      'write' => 'Write the component to the current site codebase. Will prompt to overwrite existing files; use yes to force. Use quiet to suppress output to the terminal.',
    ),
  );

  $items['mb-download'] = array(
    'callback' => 'drush_module_builder_callback_hook_download',
    'description' => "Update module_builder hook data.",
    'options' => array(
      'data' => "Location to save downloaded files. May be absolute, or relative to Drupal files dir. Defaults to 'files/hooks'.",
    ),
    'aliases' => array('mbdl'),
  );

  $items['mb-list'] = array(
    'callback' => 'drush_module_builder_callback_data_list',
    'description' => "List the hooks module_builder knows about.",
    'arguments' => array(
      'modules' => '(optional) Names of modules, separated by spaces.',
    ),
    'options' => array(
      'raw' => "Outputs the raw debug hook data.",
    ),
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function module_builder_drush_help($section) {
  switch ($section) {
    case 'drush:mb-build':
      return dt('Generates and optionally writes module code with the specified hooks. ' .
        'By default this runs in interactive mode, and will prompt you for each ' .
        "of the module's properties. Use the --noi option to use as a single command.");
  }
}

/**
 * Handle a sanity exception from the library and output a message.
 *
 * @param ModuleBuilder\Exception\SanityException $e
 *  A sanity exception object.
 */
function module_builder_handle_sanity_exception($e) {
  $failed_sanity_level = $e->getFailedSanityLevel();
  switch ($failed_sanity_level) {
    case 'data_directory_exists':
      $message = "The component data directory could not be created or is not writable.";
      break;
    case 'component_data_processed':
      $message = "No component data was found. Run 'drush mb-download' to process component data from documentation files.";
      break;
  }
  drush_set_error(DRUSH_APPLICATION_ERROR, $message);
}

/**
 * Module builder drush command callback.
 *
 * Form:
 *  $drush mb machine_name hookA hookB hookC
 *    perms: permission_name
 *    routes: path/to/thing another/path
 *    theme: my_themeable another_themeable
 *    readme! settings-form! tests!
 * Hook names may be short or long, e.g. both 'help' and 'hook_help' are
 * allowed. Parameters ending in a ':' are markers, which introduce a new type
 * of parameter, such as routes and plugins. Parameters ending in '!' are
 * booleans which add a single component such as a README file.
 */
function drush_module_builder_callback_build_module() {
  $commands = func_get_args();

  // Check settings before we start. This sort of wastes the potential of using
  // exceptions, but it's polite to warn the user of problems before they've
  // spent ages typing in all the hook names in interactive mode.
  // Get our task handler. This performs a sanity check on the environment which
  // throws an exception.
  try {
    $mb_task_handler_generate = \DrupalCodeBuilder\Factory::getTask('Generate', 'module');
  }
  catch (\DrupalCodeBuilder\Exception\SanityException $e) {
    // If the problem is that the hooks need downloading, we can recover from this.
    if ($e->getFailedSanityLevel() == 'component_data_processed') {
      if (drush_confirm(dt('No hook definitions found. Would you like to download them now?'))) {
        // Download the hooks so we can move on.
        $success = drush_module_builder_callback_hook_download();
        if (!$success) {
          drush_set_error(DRUSH_APPLICATION_ERROR, 'Problem downloading hook data.');
          return;
        }

        // Get the task handler that we were trying to get in the first place.
        $mb_task_handler_generate = \DrupalCodeBuilder\Factory::getTask('Generate', 'module');
      }
    }
    // Otherwise, fail.
    else {
      drush_set_error(DRUSH_APPLICATION_ERROR, $e->getMessage());
      return;
    }
  }

  // Extra drush-specific component data info for the module component.
  // This defines command prefixes and drush options.
  // This gets merged with the component data info returned from the Module
  // generator.
  $component_info_drush_extra = array(
    'root_name' => array(
      'drush_value_process' => 'module_builder_drush_component_root_name_process',
    ),
    'hooks' => array(
      'command_prefix' => 'hooks',
      // The list is huge, so bypass showing the options.
      'drush_no_option_list' => TRUE,
    ),
    'module_hook_presets' => array(
      'command_prefix' => 'presets',
    ),
    'readable_name' => array(
      'drush_option' => 'name',
    ),
    'short_description' => array(
      'drush_option' => 'desc',
    ),
    'module_help_text' => array(
      'drush_option' => 'helptext',
    ),
    'module_dependencies' => array(
      'drush_option' => 'dep',
    ),
    'module_package' => array(
      'drush_option' => 'package',
    ),
    'permissions' => array(
      'command_prefix' => 'perms',
    ),
    'services' => array(
      'command_prefix' => 'services',
    ),
    'plugins' => array(
      'command_prefix' => 'plugins',
    ),
    'theme_hooks' => array(
      'command_prefix' => 'theme',
    ),
    'router_items' => array(
      'command_prefix' => 'routes',
    ),
  );

  $component_data_extra = array();

  // Extra component data for the build list and the bare code options.
  // What to build.
  $build = drush_get_option('build');

  // write options:
  // - all -- everything we can do
  // - code -- code files, not info (module + install _ ..?)
  // - info -- only info fole
  // - module -- only module file
  // - install -- only install file
  // - ??? whatever hooks need

  // No build: set nice default.
  if (!$build) {
    // If we are adding, 'code' is implied
    if (drush_get_option('add')) {
      $build = 'code';
    }
    // If we are writing or going, all.
    elseif (drush_get_option(array('write', 'go'))) {
      $build = 'all';
    }
    // Otherwise, outputting to terminal: only module
    else {
      $build = 'code';
    }
  }

  // Make a list out of the build option string. This may of course have only
  // one item in it.
  $build_list = explode(' ', $build);

  // Multi build: set a single string to switch on below.
  if (count($build_list) > 1) {
    $build = 'code';
  }

  // Set the build list in the module data.
  // TODO: move all the above to a helper function!
  $component_data_extra['requested_build'] = array_fill_keys($build_list, TRUE);

  // The 'bare code' option. This doesn't fully work yet, as 'add' doesn't
  // fully work yet! TODO!
  $bare_code = drush_get_option('add');
  $component_data_extra['bare_code'] = $bare_code;

  // Insert the 'hooks' prefix into the commands array, after the module name.
  // This privileges the hooks: they don't need a prefix and all commands up to
  // another prefix are taken to be hooks.
  array_splice($commands, 1, 0, 'hooks:');

  // Call the main function to do the work of gathering data from the user and
  // building the files and optionally writing them.
  drush_module_builder_build_component($commands, 'module', $component_info_drush_extra, $component_data_extra);

  // Enable the module if requested.
  if (drush_get_option('go')) {
    pm_module_manage(array(array_shift($commands)), TRUE);
  }
}

/**
 * Command argument complete callback: mb-build.
 *
 * Debug this with, for example:
 *  $ drush --early=includes/complete.inc --complete-debug drush mb foo ini
 * and comment out cache in drush_complete_get().
 */
function module_builder_module_builder_callback_build_complete() {
  // We're too early in the drush bootstrap for this to be called, apparently.
  // So do it ourselves.
  module_builder_drush_init_helper();

  // Add our environment handler, and check hook data is ready.
  try {
    // Drupal bootstrap isn't ready enough to check environment, because the
    // file API isn't loaded. This isn't bad, because here it's us, the caller,
    // that knows we can't pass the check.
    \DrupalCodeBuilder\Factory::getEnvironment()->skipSanityCheck(TRUE);

    $mb_task_handler_report = \DrupalCodeBuilder\Factory::getTask('ReportHookData');
  }
  catch (\DrupalCodeBuilder\Exception\SanityException $e) {
    // Just do nothing if we're not ready: the actual command will deal with
    // error output to the user.
    return;
  }

  $data = $mb_task_handler_report->getHookDeclarations();
  $complete = array();
  foreach ($data as $hook_name => $hook_data) {
    $complete[] = $hook_name;

    // Add the short name too.
    $complete[] = substr($hook_name, 5);

    // TODO: in anticipation of us doing callbacks too, don't just chop off the
    // front!
    //if (preg_match('/^hook_/'))
  }

  return array(
    'values' => $complete,
  );
}

/**
 * Build a generic requested component.
 *
 * @param $component_type
 *  The type of the component, e.g. 'module'.
 */
function drush_module_builder_callback_build_component($component_type = NULL) {
  $commands = func_get_args();
  // Shift off the component name.
  array_shift($commands);

  // Determine whether we're in interactive mode.
  $interactive = !drush_get_option(array('non-interactive', 'noi'));

  // This is a shortcut for developing, if you have --noi forced in your drush
  // config and need to switch back to interactive for testing.
  if (drush_get_option(array('interactive'))) {
    $interactive = TRUE;
  }

  if (empty($component_type)) {
    if ($interactive) {
      $component_type = drush_prompt("Enter a component name", 'module', TRUE);
    }
    else {
      throw new Exception("No component name given.");
    }
  }

  drush_module_builder_build_component($commands, $component_type);
}

/**
 * Builds a Drupal component.
 *
 * This is a common helper for Drush command callbacks, that each deal with a
 * single type of component, e.g. modules or themes.
 *
 * This hands over to module_builder_drush_output_code() to output the code.
 *
 * @param $commands
 *  The commands array; the original parameters from the Drush command callback.
 * @param $component_type
 *  The component type, e.g., 'module'.
 * @param $component_info_drush_extra = array()
 *  An array to merge into the component data info array. This may may contains
 *  additional information specific to drush. Possible keys for each property
 *  info array are:
 *    - 'command_prefix': A string that when suffixed with ':' acts as a marker
 *      for this property in the commands array. All commands after this marker
 *      taken as data for this property, until another marker is found or until
 *      the end of the commands array. For example, the following list of
 *      commands contains hooks and hook presets:
 *      'hooks: menu init presets: node field-widget'
 *    - 'drush_option': A string giving the drush option to take this property's
 *      value from.
 *    - 'drush_no_option_list': Suppress the output of an option list for this
 *      property. Useful for a property whose list of options is very large.
 *    - 'drush_value_process': A callable to apply processing to the property's
 *      value before passing it to the generator. This receives the value as its
 *      parameter.
 * @param $component_data = array()
 *  Initial component data, into which data obtained from the user will be
 *  added. Command callbacks may wish to gather extra data, or add defaults.
 */
function drush_module_builder_build_component($commands, $component_type, $component_info_drush_extra = array(), $component_data = array()) {
  // Get the Generator task, specifying the component we want so we get a
  // sanity check based on that and our environment.
  try {
    $mb_task_handler_generate = \DrupalCodeBuilder\Factory::getTask('Generate', $component_type);
  }
  catch (\DrupalCodeBuilder\Exception\SanityException $e) {
    module_builder_handle_sanity_exception($e);
    return;
  }

  // Set the base type.
  $component_data['base'] = $component_type;

  // Get the component data info, and add in extra info specific to Drush.
  $component_data_info = $mb_task_handler_generate->getRootComponentDataInfo();
  // Remove extra property info that's for properties we don't know about (such
  // as ones that only apply to certain versions of Drupal).
  foreach ($component_info_drush_extra as $property_name => $property_extra_info) {
    if (!isset($component_data_info[$property_name])) {
      unset($component_info_drush_extra[$property_name]);
    }
  }
  $component_data_info = array_merge_recursive($component_data_info, $component_info_drush_extra);

  // Split the commands array into:
  //  - plain commands
  //  - a nested array of prefixed commands
  //  - an array of boolean flags.
  // E.g. given 'foo bar hooks: biz presets: bax qux!', we want:
  //  - $commands as just 'foo bar'
  //  - hooks as 'biz'
  //  - presets as 'bax'
  //  - boolean flags as 'qux'
  $plain_commands     = array();
  $prefixed_commands  = array();
  $boolean_commands   = array();
  foreach ($commands as $command) {
    if (substr($command, -1) == ':') {
      // This is a preset marker.
      $prefix_marker = substr($command, 0, -1);

      // Set the array up and move on.
      $prefixed_commands[$prefix_marker] = array();
      continue;
    }

    if (substr($command, -1) == '!') {
      // This is a boolean flag.
      $boolean_flag = substr($command, 0, -1);

      $boolean_commands[$boolean_flag] = TRUE;
      continue;
    }

    if (isset($prefix_marker)) {
      // Continue taking commands for this prefix until we find another prefix
      // marker or run out of commands.
      $prefixed_commands[$prefix_marker][] = $command;
    }
    else {
      $plain_commands[] = $command;
    }
  }

  // Determine whether we're in interactive mode.
  $interactive = !drush_get_option(array('non-interactive', 'noi'));

  // This is a shortcut for developing, if you have --noi forced in your drush
  // config and need to switch back to interactive for testing.
  if (drush_get_option(array('interactive'))) {
    $interactive = TRUE;
  }

  // Build the component data array from the given commands.
  // Work through the component data info, assembling the component data array
  // Each property info needs to be prepared, so iterate by reference.
  foreach ($component_data_info as $property_name => &$property_info) {
    // Prepare the single property: get options, default value, etc.
    $mb_task_handler_generate->prepareComponentDataProperty($property_name, $property_info, $component_data);

    // Initialize our value from the default that's been set by
    // prepareComponentDataProperty(). We try various things to set it.
    $value = $component_data[$property_name];
    // Keep track of whether the value has come from the user or is still the
    // default.
    $user_specified = FALSE;

    // If the property is required, and there are plain command parameters
    // remaining, take one of those.
    if (!$user_specified && $property_info['required'] && $plain_commands) {
      $value = array_shift($plain_commands);
      $user_specified = TRUE;
    }

    // If the property has a prefix, and the commands included that prefix,
    // then take the commands for that prefix.
    if (!$user_specified && isset($property_info['command_prefix']) && !empty($prefixed_commands[$property_info['command_prefix']])) {
      $value = $prefixed_commands[$property_info['command_prefix']];
      $user_specified = TRUE;
    }

    // If the property can be set with a command-line option, check that.
    if (!$user_specified && isset($property_info['drush_option'])) {
      $drush_option_value = drush_get_option($property_info['drush_option']);
      if (!empty($drush_option_value)) {
        $value = $drush_option_value;
        $user_specified = TRUE;
      }
    }

    // Boolean commands.
    if ($property_info['format'] == 'boolean') {
      if (isset($boolean_commands[$property_name])) {
        $value = TRUE;
        $user_specified = TRUE;
      }
    }

    // Process direct mode values for compound properties into the expected
    // format.
    if ($user_specified && ($property_info['format'] == 'compound')) {
      // A compound property in direct input mode is split up on the '-'
      // character, and the pieces are taken in order for the child properties.
      // So for example, for plugins, 'block-alpha' means:
      //  - plugin_type: block
      //  - plugin_name: alpha
      $child_property_names = array_keys($property_info['properties']);

      $items = [];
      foreach ($value as $single_value) {
        $item = [];

        $value_pieces = explode('-', $single_value);
        while ($piece = array_shift($value_pieces)) {
          $child_property_name = array_shift($child_property_names);
          $item[$child_property_name] = $piece;
        }

        $items[] = $item;
        // Defaults for other child properties will be filled in by the
        // process stage.
      }
      $value = $items;
    }

    // If we're not in interactive mode, there's nothing more to do for this
    // command other than use the default value. That's already set in the
    // component data.

    if (!$user_specified && $interactive) {
      // Prompt the user for a property we've not already got user input for.

      // Turn empty defaults into a value that Drush won't output.
      $default = empty($component_data[$property_name]) ? NULL : $component_data[$property_name];

      if ($property_info['format'] == 'compound') {
        // For compound properties, allow the user to enter as many items as
        // they like, prompting for all the child properties for each item.
        // Entering an empty value for the first child property ends the
        // handling of the compound property.
        $delta = 0;
        $child_property_names = array_keys($property_info['properties']);
        while (TRUE) {
          foreach ($property_info['properties'] as $child_property_name => &$child_property_info) {
            // Override the required property of the first child property, so
            // the user can leave it empty and break out of this loop.
            $first_child = ($child_property_name == $child_property_names[0]);

            // Add the parent label so we can use it in prompts.
            $child_property_info['parent_label'] = $property_info['label'];

            $child_value = module_builder_drush_interactive_prompt($child_property_name, $child_property_info, $component_data[$child_property_name], $default, $first_child);

            // If the first property is empty, stop collecting child items.
            if ($first_child && empty($child_value)) {
              // Bail on both the while and the foreach loops: XKCD dinosaur!
              goto endcompound;
            }

            $value[$delta][$child_property_name] = $child_value;
          }

          $delta++;
        }
        endcompound:
      }
      else {
        $value = module_builder_drush_interactive_prompt($property_name, $property_info, $component_data, $default);
      }
    } // End interactive.

    // Split up the value if it should be an array.
    if ($property_info['format'] == 'array' && !is_array($value)) {
      $value = preg_split('/\s+/', $value, -1,  PREG_SPLIT_NO_EMPTY);
    }

    // Process compound properties into the expected format.
    if ($property_info['format'] == 'compound' && !is_array($value)) {
      $value = preg_split('/\s+/', $value, -1,  PREG_SPLIT_NO_EMPTY);

      // For now, a compound property is input in direct mode as a flat array,
      // where we take each array element to be the first child property.
      $child_properties = $property_info['properties'];
      $first_child_property = array_shift(array_keys($child_properties));

      $items = [];
      foreach ($value as $single_value) {
        $items[][$first_child_property] = $single_value;
        // Defaults for other child properties will be filled in by the
        // process stage.
      }
      $value = $items;
    }

    // Perform any processing specific to drush.
    if (isset($property_info['drush_value_process'])) {
      $callback = $property_info['drush_value_process'];
      $value = $callback($value);
    }

    // Set the value in the component data array.
    $component_data[$property_name] = $value;
  }

  // Perform any final processing on the component data.
  // This prepares data, for example expands options such as hook presets.
  $mb_task_handler_generate->processComponentData($component_data_info, $component_data);

  // Generate the component.
  $files = $mb_task_handler_generate->generateComponent($component_data);

  $component_dir = module_builder_get_component_folder($component_type, $component_data['root_name']);

  // Finally, output the files!
  foreach ($files as $filename => $code) {
    module_builder_drush_output_code($component_dir, $filename, $code);
  }
}

/**
 * Prompt the user for a single value.
 *
 * This can be used recursively for child properties.
 *
 * @param $property_name
 *  The name of the property to prompt for.
 * @param $property_info
 *  The info array for a single component property.
 * @param $component_data
 *  The component data array, or a sub-array of it, such that the property's
 *  value will be placed in the top level of this array.
 * @param $default
 *  The default value for the property.
 * @param $first_child
 *  (optional) If the property is a child property, TRUE to indicate it's the
 *  first of the children. Defaults to FALSE.
 *
 * @return
 *  The value obtained from the user.
 */
function module_builder_drush_interactive_prompt($property_name, $property_info, $component_data, $default, $first_child = FALSE) {
  // Show the user the available options, unless told not to.
  $numeric_options = FALSE;
  if (isset($property_info['options']) && empty($property_info['drush_no_option_list'])) {
    $numeric_options = TRUE;
    $option_by_index = array();
    $i = 1;
    foreach ($property_info['options'] as $option => $label) {
      $option_by_index[$i] = $option;
      drush_print("[$i] $option: $label", 2);

      $i++;
    }
  }

  // Make a prompt string, letting the user know if the input is optional
  // so they don't waste time typing.
  switch ($property_info['format']) {
    case 'array':
      $prompt = "Enter the {$property_info['label']}, as a space separated list";
      break;
    case 'boolean':
      $prompt = "Do you want a {$property_info['label']}";
      $prompt .= ' ' . "(y/n)";
      // Set the default.
      $default = empty($default) ? 'n' : 'y';
      break;
    case 'string':
      $prompt = "Enter the {$property_info['label']}";
  }
  if (empty($property_info['required']) && empty($component_data[$property_name])) {
    $prompt .= ' ' . "(optional)";
  }
  if ($first_child) {
    $prompt .= ' ' . "(leave blank to finish entering {$property_info['parent_label']})";
  }

  // Hack the 'required' property for first child, so the user can enter an
  // empty string and exit the child properties loop.
  $required = $property_info['required'];
  if ($first_child) {
    $required = FALSE;
  }

  $value = drush_prompt($prompt, $default, $required);

  // Split a space-separated list into an array.
  if ($property_info['format'] == 'array') {
    $value = preg_split('@\s+@', $value);
  }

  // Callback to convert a value given as an index in a list of options into
  // the actual option value.
  $convert_index_to_value_callback = function($item) use ($option_by_index) {
    if (is_numeric($item)) {
      return $option_by_index[$item];
    }
    else {
      return $item;
    }
  };

  // Convert a numeric option index value into the actual option value.
  if ($numeric_options) {
    // We have either an array of values or a single one.
    if (is_array($value)) {
      $value = array_map($convert_index_to_value_callback, $value);
    }
    else {
      $value = $convert_index_to_value_callback($value);
    }
  }

  return $value;
}

/**
 * Process the input for component base name for filesystem shorthands.
 *
 * This allows for the following in the component name:
 *  - A single '.' specifies the current folder.
 *  - A trailing slash is ignored, thus allowing autocompletion of folder names.
 *
 * @param $component_root_name
 *  The given component root name.
 *
 * @return
 *  The processed component root name.
 */
function module_builder_drush_component_root_name_process($component_root_name) {
  // Trim a final '/' from the module machine name, to allow use of tab
  // autocompletion on the command line.
  if (substr($component_root_name, -1) == '/') {
    $component_root_name = substr($component_root_name, 0, -1);
  }

  // An input machine name given as '.' means use the current folder as the
  // component name, thus write to the current folder.
  if ($component_root_name == '.') {
    $component_root_name = basename(drush_get_context('DRUSH_OLDCWD'));
  }

  return $component_root_name;
}

/**
 * Get the folder where a generated component's files should be written to.
 *
 * @param $component_type
 *  The type of the component. One of 'module' or 'theme'.
 * @param $component_name
 *  The component name.
 *
 * @return
 *  The full system path for the component's folder, without a trailing slash.
 */
function module_builder_get_component_folder($component_type, $component_name) {
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  // First try: if the component exists, we write there: nice and simple.
  $component_path = drupal_get_path($component_type, $component_name);
  if (!empty($component_path)) {
    return $drupal_root . '/' . $component_path;
  }

  // Third try: 'parent' option was given.
  if (drush_get_option('parent')) {
    // The --parent option allows the user to specify a location for the new module folder.
    $parent_dir = drush_get_option('parent');
    if (substr($parent_dir, 0 , 1) == '.') {
      // An initial . means to start from the current directory rather than
      // the modules folder, which allows submodules to be created where the
      // user is standing.
      $module_dir = drush_get_context('DRUSH_OLDCWD') . '/';
      // Remove both the . and the following /.
      $parent_dir = substr($parent_dir, 2);
      if ($parent_dir) {
        // If there's anything left (since just '.' is a valid option), append it.
        $module_dir .= $parent_dir . '/';
      }
    }
    else {
      // If there's no dot, assume that an existing module is meant.
      // (Would anyone enter a complete path for this??? If we do need this,
      // then consider recursing into this for the parent path??)
      $module_dir .= drupal_get_path($component_type, $parent_dir) . '/';
    }
    return $module_dir . $component_name;
  }

  // Fourth and final try: build it based on the module folder structure.

  // There is probably a proper way to do this but it's Sunday morning and
  // I want this to just work and so brute force appeals.
  require_once DRUSH_BASE_PATH . '/commands/pm/download.pm.inc';
  $module_dir = _pm_download_destination($component_type);

  // Some versions of Drush don't give us the trailing /.
  if (substr($module_dir, -1, 1) != '/') {
    $module_dir .= '/';
  }

  if ($component_type == 'module') {
    // Drush tries to put any module into 'contrib' if the folder exists;
    // hack this out and put the code in 'custom'.
    if (substr($module_dir, -8, 7) == 'contrib') {
      $module_dir_custom = substr_replace($module_dir, 'custom', -8, 7);
      if (is_dir($module_dir_custom)) {
        $module_dir = $module_dir_custom;
      }
    }
  }

  // $module_dir should now be a full path to the parent of the destination
  // folder, with a trailing slash.
  $module_dir .= $component_name;

  return $module_dir;
}

/**
 * Output generated text, to terminal or to file.
 *
 * @param $component_dir
 *  The base folder for the component. May or may not exist.
 * @param $filename
 *  The filename to write, relative to the $component_dir.
 * @param code
 *  The text to write to the file.
 */
function module_builder_drush_output_code($component_dir, $filename, $code) {
  // Output to terminal
  if (!drush_get_option('quiet')) {
    drush_print("Proposed $filename:");
    drush_print_r($code);
  }

  $write = drush_get_option('write');

  // Write to file
  // Add to file option implies write.
  // Write & go option implies write.
  if (drush_get_option(array('write', 'add', 'go'))) {
    $filepath = $component_dir . '/' . $filename;

    // Because the filename part can contain subdirectories, check these exist
    // too.
    $subdir = dirname($filepath);
    if (!is_dir($subdir)) {
      $result = mkdir($subdir, 0777, TRUE);
      if ($result && !drush_get_option('quiet')) {
        if ($subdir == $component_dir) {
          drush_print("Module directory $component_dir created");
        }
        else {
          drush_print("Module subdirectory $subdir created");
        }
      }
    }

    // Add to file option.
    // If the file doesn't exist, we skip this and silently write it anyway.
    if (drush_get_option('add') && file_exists($filepath)) {
      $fh = fopen($filepath, 'a');
      fwrite($fh, $code);
      fclose($fh);
      return;
    }

    // If the file exists, ask for whether to overwrite.
    if (file_exists($filepath)) {
      if (!drush_confirm(dt('File ' . $filename . ' exists. Do you really want to overwrite?'))) {
        return;
      }
    }

    file_put_contents($filepath, $code);
  }
}

/**
 * Callback for downloading hook data.
 *
 * @return
 *  Boolean indicating TRUE for success, FALSE for failure.
 */
function drush_module_builder_callback_hook_download() {
  // Get our task handler. This performs a sanity check which throws an
  // exception.
  try {
    $mb_task_handler_collect = \DrupalCodeBuilder\Factory::getTask('Collect');

    // Hidden option for developers: downloads a subset of hooks to create the
    // data for Drupal Code Builder's unit tests.
    if (drush_get_option('test')) {
      $mb_task_handler_collect = \DrupalCodeBuilder\Factory::getTask('Testing\CollectTesting');
    }
  }
  catch (\DrupalCodeBuilder\Exception\SanityException $e) {
    module_builder_handle_sanity_exception($e);
  }

  $mb_task_handler_collect->collectComponentData();

  $hooks_directory = \DrupalCodeBuilder\Factory::getEnvironment()->getHooksDirectory();
  drush_print("Hook files have been downloaded to {$hooks_directory} and processed.");
  return TRUE;
}

/**
 * Callback to list stored data on components.
 */
function drush_module_builder_callback_data_list() {
  $commands = func_get_args();

  // Get our task handler, which checks hook data is ready.
  try {
    $mb_task_handler_report = \DrupalCodeBuilder\Factory::getTask('ReportHookData');
  }
  catch (\DrupalCodeBuilder\Exception\SanityException $e) {
    module_builder_handle_sanity_exception($e);
    return;
  }

  $time = $mb_task_handler_report->lastUpdatedDate();
  $data = $mb_task_handler_report->listHookData();

  if (drush_get_option('raw')) {
    drush_print_r($data);
    return;
  }

  if (count($commands)) {
    // Put the requested filenames into the keys of an array, and intersect them
    // with the hook data.
    $files_requested = array_fill_keys($commands, TRUE);
    $data_requested = array_intersect_key($data, $files_requested);
  }
  else {
    $data_requested = $data;
  }

  if (!count($data_requested) && count($files_requested)) {
    drush_print(t("No hooks found for the specified files."));
  }

  drush_print("Hooks:");
  foreach ($data_requested as $file => $hooks) {
    drush_print("Group $file:", 2);
    foreach ($hooks as $key => $hook) {
      drush_print($hook['name'] . ': ' . $hook['description'], 4);
    }
  }

  // List presets.
  try {
    $mb_task_handler_report_presets = \DrupalCodeBuilder\Factory::getTask('ReportHookPresets');
  }
  catch (\DrupalCodeBuilder\Exception\SanityException $e) {
    module_builder_handle_sanity_exception($e);
    return;
  }

  $hook_presets = $mb_task_handler_report_presets->getHookPresets();
  foreach ($hook_presets as $hook_preset_name => $hook_preset_data) {
    drush_print("Preset $hook_preset_name: " . $hook_preset_data['label'], 2);
    foreach ($hook_preset_data['hooks'] as $hook) {
      drush_print($hook, 4);
    }
  }

  if (drush_drupal_major_version() == 8) {
    try {
      $mb_task_handler_report_plugins = \DrupalCodeBuilder\Factory::getTask('ReportPluginData');
    }
    catch (\DrupalCodeBuilder\Exception\SanityException $e) {
      module_builder_handle_sanity_exception($e);
      return;
    }

    $data = $mb_task_handler_report_plugins->listPluginData();

    drush_print("Plugins:");
    foreach ($data as $plugin_type_id => $plugin_type_data) {
      drush_print($plugin_type_id, 2);
    }
  }

  $hooks_directory = \DrupalCodeBuilder\Factory::getEnvironment()->getHooksDirectory();
  drush_print(t("Component data retrieved from @dir.", array('@dir' => $hooks_directory)));
  drush_print(t("Component data was processed on @time.", array(
    '@time' => date(DATE_RFC822, $time),
  )));
}
