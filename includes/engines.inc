<?php

/**
 * @file
 * The drush engines API implementation and helpers.
 */

/**
 * Obtain all engine types info and normalize with defaults.
 *
 * @see hook_drush_engine_type_info().
 */
function drush_get_engine_types_info() {
  $info = drush_command_invoke_all('drush_engine_type_info');
  foreach ($info as $type => $data) {
    $info[$type] += array(
      'description' => '',
      'option' => FALSE,
      'default' => NULL,
      'options' => array(),
      'config-aliases' => array(),
      'add-options-to-command' => FALSE,
      'combine-help' => FALSE,
    );
  }

  return $info;
}

/**
 * Return a structured array of engines of a specific type.
 *
 * Engines are pluggable subsystems. Each engine of a specific type will
 * implement the same set of API functions and perform the same high-level
 * task using a different backend or approach.
 *
 * This function/hook is useful when you have a selection of several mutually
 * exclusive options to present to a user to select from.
 *
 * Other commands are able to extend this list and provide their own engines.
 * The hook can return useful information to help users decide which engine
 * they need, such as description or list of available engine options.
 *
 * The engine path element will automatically default to a subdirectory (within
 * the directory of the commandfile that implemented the hook) with the name of
 * the type of engine - e.g. an engine "wget" of type "handler" provided by
 * the "pm" commandfile would automatically be found if the file
 * "pm/handler/wget.inc" exists and a specific path is not provided.
 *
 * @param $engine_type
 *   The type of engine.
 *
 * @return
 *   A structured array of engines.
 */
function drush_get_engines($engine_type) {
  $info = drush_get_engine_types_info();
  if (!isset($info[$engine_type])) {
    return drush_set_error('DRUSH_UNKNOWN_ENGINE_TYPE', dt('Unknown engine type !engine_type', array('!engine_type' => $engine_type)));
  }

  $engines = array(
    'info' => $info[$engine_type],
    'engines' => array(),
  );
  $list = drush_commandfile_list();
  $hook = 'drush_engine_' . str_replace('-', '_', $engine_type);
  foreach ($list as $commandfile => $path) {
    if (drush_command_hook($commandfile, $hook)) {
      $function = $commandfile . '_' . $hook;
      $result = $function();
      foreach ($result as $key => $engine) {
        // Add some defaults
        $engine += array(
          'commandfile' => $commandfile,
          // Engines by default live in a subdirectory of the commandfile that
          // declared them, named as per the type of engine they are.
          'path' => sprintf("%s/%s", dirname($path), $engine_type),
        );
        $engines['engines'][$key] = $engine;
      }
    }
  }
  return $engines;
}

/**
 * Take a look at all of the available engines,
 * and create topic commands for each one that
 * declares a topic.
 */
function drush_get_engine_topics() {
  $items = array();
  $info = drush_get_engine_types_info();
  foreach ($info as $engine => $data) {
    if (array_key_exists('topic', $data)) {
      $items[$data['topic']] = array(
        'description' => $data['description'],
        'hidden' => TRUE,
        'topic' => TRUE,
        'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
        'callback' => 'drush_engine_topic_command',
        'callback arguments' => array($engine),
      );
    }
  }
  return $items;
}

/**
 * Include, instantiate and validate command engines.
 *
 * @return FALSE if a engine doesn't validate.
 */
function drush_load_command_engines($command) {
  foreach ($command['engines'] as $engine_type => $config) {
    $result = drush_load_command_engine($command, $engine_type);
  }
}

function drush_get_command_engine_config($command, $engine_type, $metadata = array()) {
  if (isset($command['engines'][$engine_type])) {
    $metadata = array_merge($metadata, $command['engines'][$engine_type]);
  }
  return $metadata;
}

function drush_load_command_engine($command, $engine_type, $metadata = array()) {
  $config = drush_get_command_engine_config($command, $engine_type, $metadata);
  $version = drush_drupal_major_version();
  drush_log(dt("Loading !engine engine.", array('!engine' => $engine_type), 'bootstrap'));
  $engine_info = drush_get_engines($engine_type);
  $selected_engine = drush_get_user_selected_engine($config, $engine_info);
  $result = drush_get_context($engine_type . '_engine_' . $selected_engine . '_' . $version, FALSE);
  if ($result != FALSE) {
    drush_set_engine($engine_type, $result);
  }
  else {
    $result = drush_load_engine($engine_type, $selected_engine, NULL, NULL, $config);
    if ($result == FALSE) {
      return FALSE;
    }
    drush_set_context($engine_type . '_engine_' . $selected_engine . '_' . $version, $result);
  }
  return $result;
}

/**
 * Add command structure info from each engine back into the command.
 */
function drush_merge_engine_data(&$command) {
  // First remap engine data from the shortcut location
  // ($command['engine_type']) to the standard location
  // ($command['engines']['engine_type'])
  $info = drush_get_engine_types_info();
  foreach ($info as $engine_type => $info) {
    if (isset($command[$engine_type])) {
      $config = $command[$engine_type];
      foreach ($info['config-aliases'] as $engine_option_alias_name => $engine_option_standard_name) {
        if (array_key_exists($engine_option_alias_name, $config)) {
          $config[$engine_option_standard_name] = $config[$engine_option_alias_name];
          unset($config[$engine_option_alias_name]);
        }
      }
      // Convert single string values of 'require-engine-capability' to an array.
      if (isset($config['require-engine-capability']) && is_string($config['require-engine-capability'])) {
        $config['require-engine-capability'] = array($config['require-engine-capability']);
      }
      $command['engines'][$engine_type] = $config;
    }
  }
  foreach ($command['engines'] as $engine_type => $config) {
    // Normalize engines structure.
    if (!is_array($config)) {
      unset($command['engines'][$engine_type]);
      $command['engines'][$config] = array();
      $engine_type = $config;
    }

    // Get all implementations for this engine type.
    $engine_info = drush_get_engines($engine_type);
    if ($engine_info === FALSE) {
      return FALSE;
    }

    // Override engine_info with customizations in the command.
    $config = $command['engines'][$engine_type] += $engine_info['info'];

    // Add engine type global options to the command.
    $command['options'] += $config['options'];
    // If the 'combine-help' flag is set in the engine config,
    // then we will combine all of the help items into the help
    // text for $config['option'].
    $combine_help = $config['combine-help'];

    $engine_data = array();

    // If there's a single implementation for this engine type, it will be
    // loaded by default, and makes no sense to provide a command line option
    // to select the only flavor (ie. --release_info=updatexml), so we won't
    // add an option in this case.
    // Additionally, depending on the command, it may be convenient to extend
    // the command with the engine options.
    if (count($engine_info['engines']) == 1) {
      if ($config['add-options-to-command'] !== FALSE) {
        $engine = key($engine_info['engines']);
        $data = $engine_info['engines'][$engine];
        foreach (array('options', 'sub-options') as $key) {
          if (isset($data[$key])) {
            $engine_data[$key] = $data[$key];
          }
        }
      }
    }
    // Otherwise, provide a command option to choose between engines and add
    // the engine options and sub-options.
    else {
      // Process engines in order. First the default engine, the rest alphabetically.
      $default = drush_find_engine_to_use($config, $engine_info);
      $engines = array_keys($engine_info['engines']);
      asort($engines);
      array_unshift($engines, $default);
      $engines = array_unique($engines);

      $engine_data += array(
        'options' => array(),
        'sub-options' => array(),
      );
      $combine_help_data = array();
      foreach ($engines as $engine) {
        $data = $engine_info['engines'][$engine];
        // Check to see if the command requires any particular
        // capabilities.  If no capabilities are required, then
        // all engines are acceptable.
        $engine_is_usable = TRUE;
        if (array_key_exists('require-engine-capability', $config)) {
          // See if the engine declares that it provides any
          // capabilities.  If no capabilities are listed, then
          // it is assumed that the engine can satisfy all requirements.
          if (array_key_exists('engine-capabilities', $data)) {
            $engine_is_usable = FALSE;
            // If 'require-engine-capability' is TRUE instead of an array,
            // then only engines that are universal (do not declare any
            // particular capabilities) are usable.
            if (is_array($config['require-engine-capability'])) {
              foreach ($config['require-engine-capability'] as $required) {
                // We need an engine that provides any one of the requirements.
                if (in_array($required, $data['engine-capabilities'])) {
                  $engine_is_usable = TRUE;
                }
              }
            }
          }
        }
        if ($engine_is_usable) {
          $command['engines'][$engine_type]['usable'][] = $engine;
          if (!isset($data['hidden'])) {
            $option = $config['option'] . '=' . $engine;
            $engine_data['options'][$option]['description'] = array_key_exists('description', $data) ? $data['description'] : NULL;
            if ($combine_help) {
              $engine_data['options'][$option]['hidden'] = TRUE;
              if (drush_get_context('DRUSH_VERBOSE') || ($default == $engine) || !isset($data['verbose-only'])) {
                $combine_help_data[$engine] = $engine . ': ' . $data['description'];
              }
            }
            if (isset($data['options'])) {
              $engine_data['sub-options'][$option] = $data['options'];
            }
            if (isset($data['sub-options'])) {
              $engine_data['sub-options'] += $data['sub-options'];
            }
          }
        }
      }
      if (!empty($combine_help_data)) {
        $engine_selection_option = $config['option'];
        if (!is_array($command['options'][$engine_selection_option])) {
          $command['options'][$engine_selection_option] = array('description' => $command['options'][$engine_selection_option]);
        }
        if (drush_get_context('DRUSH_VERBOSE')) {
          $command['options'][$engine_selection_option]['description'] .= "\n" . dt("All available values are:") . "\n - " . implode("\n - ", $combine_help_data) . "\n";
        }
        else {
          $command['options'][$engine_selection_option]['description'] .= " " . dt("Available: ") . implode(', ', array_keys($combine_help_data)) . ". ";
        }
        $command['options'][$engine_selection_option]['description'] .= dt("Default is !default.", array('!default' => $default));
      }
      else {
        // If the help options are not combined, then extend the
        // default engine description.
        $desc = $engine_info['engines'][$default]['description'];
        $engine_info['engines'][$default]['description'] = dt('Default !type engine.', array('!type' => $engine_type)) . ' ' . $desc;
      }
    }
    $command = array_merge_recursive($command, $engine_data);
  }
}

/**
 * Implementation of command hook for docs-output-formats
 */
function drush_engine_topic_command($engine) {
  $engine_instances = drush_get_engines($engine);
  $option = $engine_instances['info']['option'];

  if (isset($engine_instances['info']['topic-file'])) {
    // To do: put this file next to the commandfile that defines the
    // engine type, not in the core docs directory.
    $docs_dir = drush_get_context('DOC_PREFIX', DRUSH_BASE_PATH);
    $path = $engine_instances['info']['topic-file'];
    $docs_file = (drush_is_absolute_path($path) ? '' : $docs_dir . '/') . $path;
    $doc_text = drush_html_to_text(file_get_contents($docs_file));
  }
  elseif (isset($engine_instances['info']['topic-text'])) {
    $doc_text = $engine_instances['info']['topic-text'];
  }
  else {
    return drush_set_error('DRUSH_BAD_ENGINE_TOPIC', dt("The engine !engine did not define its topic command correctly.", array('!engine' => $engine)));
  }

  // Look at each instance of the engine; if it has an html
  // file in the the 'topics' folder named after itself, then
  // include the file contents in the engine topic text.
  $instances = $engine_instances['engines'];
  ksort($instances);
  foreach ($instances as $instance => $config) {
    if (isset($config['description'])) {
      $doc_text .= "\n\n::: --$option=$instance :::\n" . $config['description'];
      $path = $config['path'] . '/topics/' . $instance . '.html';
      if (file_exists($path)) {
        $doc_text .= "\n" . drush_html_to_text(file_get_contents($path));
      }
      $additional_topic_text = drush_command_invoke_all('drush_engine_topic_additional_text', $engine, $instance, $config);
      if (!empty($additional_topic_text)) {
        $doc_text .= "\n\n" . implode("\n\n", $additional_topic_text);
      }
    }
  }

  // Write the topic text to a file so that is can be paged
  $file = drush_save_data_to_temp_file($doc_text);
  drush_print_file($file);
}

function drush_get_user_selected_engine(&$config, $engine_info) {
  $engines = array_keys($engine_info['engines']);
  $default = isset($config['default']) ? $config['default'] : current($engines);
  if (!empty($config['option'])) {
    $selected_engine = drush_get_option($config['option'], $default);
  }
  // Otherwise the default engine is the only option.
  else {
    $selected_engine = $default;
  }
  return $selected_engine;
}

function drush_find_engine_to_use(&$config, $engine_info, $selected_engine = NULL) {
  if (!isset($selected_engine)) {
    $selected_engine = $config['default'];
  }
  if (array_key_exists($selected_engine, $engine_info['engines'])) {
    $config['engine-info'] = $engine_info['engines'][$selected_engine];
  }
  return $selected_engine;
}

function drush_load_engine($type, $engine, $version = NULL, $path = NULL, $engine_config = NULL) {
  $engine_info = drush_get_engines($type);
  $selected_engine = drush_find_engine_to_use($engine_config, $engine_info, $engine);
  if (!array_key_exists($selected_engine, $engine_info['engines'])) {
    return drush_set_error('DRUSH_UNKNOWN_ENGINE_TYPE', dt('Unknown !engine_type engine !engine', array('!engine' => $engine, '!engine_type' => $type)));
  }

  $result = drush_include_engine($type, $selected_engine, $version, $path, $engine_config);
  if (is_object($result)) {
    $valid = method_exists($result, 'validate') ? $result->validate() : TRUE;
    if ($valid) {
      drush_set_engine($type, $result);
    }
  }
  else {
    $function = strtr($type, '-', '_') . '_validate';
    $valid = function_exists($function) ? call_user_func($function) : TRUE;
  }
  if (!$valid) {
    return FALSE;
  }
  return $result;
}

/**
 * Include the engine code for a specific named engine of a certain type.
 *
 * If the engine type has implemented hook_drush_engine_$type the path to the
 * engine specified in the array will be used.
 *
 * If a class named in the form drush_$type_$engine exists, it will be an
 * object of that class will be created and returned.
 *
 * If you don't need to present any user options for selecting the engine
 * (which is common if the selection is implied by the running environment)
 * and you don't need to allow other modules to define their own engines you can
 * simply pass the $path to the directory where the engines are, and the
 * appropriate one will be included.
 *
 * Unlike drush_include this function will set errors if the requested engine
 * cannot be found.
 *
 * @param $type
 *   The type of engine.
 * @param $engine
 *   The key for the engine to be included.
 * @param $version
 *   The version of the engine to be included - defaults to the current Drupal core
 *   major version.
 * @param $path
 *   A path to include from, if the engine has no corresponding
 *   hook_drush_engine_$type item path.
 * @return TRUE or instanced object of available class on success. FALSE on fail.
 */
function drush_include_engine($type, $selected_engine, $version = NULL, $path = NULL, $engine_config = NULL) {
  $engine_info = drush_get_engines($type);
  $engine = isset($engine_info['engines'][$selected_engine]['engine-class']) ? $engine_info['engines'][$selected_engine]['engine-class'] : $selected_engine;
  if (!$path && isset($engine_info['engines'][$engine])) {
    $path = $engine_info['engines'][$engine]['path'];
  }
  if (!$path) {
    return drush_set_error('DRUSH_ENGINE_INCLUDE_NO_PATH', dt('No path was set for including the !type engine !engine.', array('!type' => $type, '!engine' => $engine)));
  }
  if (drush_include($path, $engine, $version)) {
    $class = 'drush_' . $type . '_' . str_replace('-', '_', $engine);
    if (class_exists($class)) {
      $instance = new $class($engine_config);
      $instance->engine_type = $type;
      $instance->engine = $engine;
      $instance->selected_engine = $selected_engine;
      return $instance;
    }
    return TRUE;
  }
  return drush_set_error('DRUSH_ENGINE_INCLUDE_FAILED', dt('Unable to include the !type engine !engine from !path.' , array('!path' => $path, '!type' => $type, '!engine' => $engine)));
}

/**
 * Return the engine of the specified type that was loaded
 * by the Drush command.
 */
function drush_get_engine($type) {
  return drush_get_context($type . '_engine', FALSE);
}

/**
 * Called by the Drush command (@see _drush_load_command_engines())
 * to cache the active engine instance.
 */
function drush_set_engine($type, $instance) {
  drush_set_context($type . '_engine', $instance);
}

/**
 * Add engine topics to the command topics, if any.
 */
function drush_engine_add_help_topics(&$command) {
  $engine_types = drush_get_engine_types_info();
  foreach ($command['engines'] as $engine_type => $config) {
    $info = $engine_types[$engine_type];
    if (isset($info['topics'])) {
      $command['topics'] = array_merge($command['topics'], $info['topics']);
    }
    if (isset($info['topic'])) {
      $command['topics'][] = $info['topic'];
    }
  }
}
