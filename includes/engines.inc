<?php

/**
 * @file
 * The drush engines API implementation and helpers.
 */

use Drush\Log\LogLevel;

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
      'sub-options' => array(),
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
      foreach ($result as $engine_name => $engine) {
        // Add some defaults.
        $engine += array(
          'commandfile' => $commandfile,
          'options' => array(),
          'sub-options' => array(),
          'drupal dependencies' => array(),
        );

        // Legacy engines live in a subdirectory
        // of the commandfile that declared them.
        $engine_path = sprintf("%s/%s", dirname($path), $engine_type);
        if (file_exists($engine_path)) {
          $engine['path'] = $engine_path;
        }
        // Build engine class name, in case the engine doesn't provide it.
        // The class name is based on the engine type and name, converted
        // from snake_case to CamelCase.
        // For example for type 'package_handler' and engine 'git_drupalorg'
        // the class is \Drush\PackageHandler\GitDrupalorg
        elseif (!isset($engine['class'])) {
          $parts = array();
          $parts[] = '\Drush';
          $parts[] = str_replace(' ', '', ucwords(strtr($engine_type, '_', ' ')));
          $parts[] = str_replace(' ', '', ucwords(strtr($engine_name, '_', ' ')));
          $engine['class'] = implode('\\', $parts);
        }

        $engines['engines'][$engine_name] = $engine;
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
  $result = TRUE;
  foreach ($command['engines'] as $engine_type => $config) {
    $result = drush_load_command_engine($command, $engine_type);
    // Stop loading engines if any of them fails.
    if ($result === FALSE) {
      break;
    }
  }
  return $result;
}

/**
 * Returns engine config supplied in the command definition.
 */
function drush_get_command_engine_config($command, $engine_type, $metadata = array()) {
  if (isset($command['engines'][$engine_type])) {
    $metadata = array_merge($metadata, $command['engines'][$engine_type]);
  }
  return $metadata;
}

/**
 * Selects and loads an engine implementing the given type.
 *
 * Loaded engines are stored as a context.
 */
function drush_load_command_engine($command, $engine_type, $metadata = array()) {
  drush_log(dt("Loading !engine engine.", array('!engine' => $engine_type), LogLevel::BOOTSTRAP));

  $config = drush_get_command_engine_config($command, $engine_type, $metadata);
  $engine_info = drush_get_engines($engine_type);
  $engine = drush_select_engine($config, $engine_info);
  $version = drush_drupal_major_version();

  $context = $engine_type . '_engine_' . $engine . '_' . $version;
  $instance = drush_get_context($context, FALSE);
  if ($instance != FALSE) {
    drush_set_engine($engine_type, $instance);
  }
  else {
    $instance = drush_load_engine($engine_type, $engine, $config);
    if ($instance == FALSE) {
      return FALSE;
    }
    drush_set_context($context, $instance);
  }
  return $instance;
}

/**
 * Add command structure info from each engine type back into the command.
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

    // Complete command-declared engine type with default info.
    $command['engines'][$engine_type] += $engine_info['info'];
    $config = $command['engines'][$engine_type];

    $engine_data = array();

    // If there's a single implementation for this engine type, it will be
    // loaded by default, and makes no sense to provide a command line option
    // to select the only flavor (ie. --release_info=updatexml), so we won't
    // add an option in this case.
    // Additionally, depending on the command, it may be convenient to extend
    // the command with the engine options.
    if (count($engine_info['engines']) == 1) {
      if ($config['add-options-to-command'] !== FALSE) {
        // Add options and suboptions of the engine type and
        // the sole implementation.
        $engine = key($engine_info['engines']);
        $data = $engine_info['engines'][$engine];
        $engine_data += array(
          'options' => $config['options'] + $data['options'],
          'sub-options' => $config['sub-options'] + $data['sub-options'],
        );
      }
    }
    // Otherwise, provide a command option to choose between engines and add
    // the engine options and sub-options.
    else {
      // Add engine type global options and suboptions.
      $engine_data += array(
        'options' => $config['options'],
        'sub-options' => $config['sub-options'],
      );

      // If the 'combine-help' flag is set in the engine config,
      // then we will combine all of the help items into the help
      // text for $config['option'].
      $combine_help = $config['combine-help'];
      $combine_help_data = array();

      // Process engines in order. First the default engine, the rest alphabetically.
      $default = drush_select_engine($config, $engine_info);
      $engines = array_keys($engine_info['engines']);
      asort($engines);
      array_unshift($engines, $default);
      $engines = array_unique($engines);
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
        if (!is_array($engine_data['options'][$engine_selection_option])) {
          $engine_data['options'][$engine_selection_option] = array('description' => $config['options'][$engine_selection_option]);
        }
        if (drush_get_context('DRUSH_VERBOSE')) {
          $engine_data['options'][$engine_selection_option]['description'] .= "\n" . dt("All available values are:") . "\n - " . implode("\n - ", $combine_help_data) . "\n";
        }
        else {
          $engine_data['options'][$engine_selection_option]['description'] .= " " . dt("Available: ") . implode(', ', array_keys($combine_help_data)) . ". ";
        }
        $engine_data['options'][$engine_selection_option]['description'] .= dt("Default is !default.", array('!default' => $default));
      }
      else {
        // If the help options are not combined, then extend the
        // default engine description.
        $desc = $engine_info['engines'][$default]['description'];
        $engine_info['engines'][$default]['description'] = dt('Default !type engine.', array('!type' => $engine_type)) . ' ' . $desc;
      }
    }
    // This was simply array_merge_recursive($command, $engine_data), but this
    // function has an undesirable behavior when merging primative types.
    // If there is a 'key' => 'value' in $command, and the same 'key' => 'value'
    // exists in $engine data, then the result will be 'key' => array('value', 'value');.
    // This is NOT what we want, so we provide our own 'overlay' function instead.
    $command = _drush_array_overlay_recursive($command, $engine_data);
  }
}

// Works like array_merge_recursive(), but does not convert primative
// types into arrays.  Ever.
function _drush_array_overlay_recursive($a, $b) {
  foreach ($b as $key => $value) {
    if (!isset($a[$key]) || !is_array($a[$key])) {
      $a[$key] = $b[$key];
    }
    else {
      $a[$key] = _drush_array_overlay_recursive($a[$key], $b[$key]);
    }
  }
  return $a;
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

/**
 * Selects an engine between the available ones.
 *
 * Precedence:
 *
 *  - preferred engine, if available.
 *  - user supplied engine via cli.
 *  - default engine from engine type / command declaration.
 *  - the first engine available.
 *
 * @param array $config
 *   Engine type configuration. My be overridden in command declaration.
 * @param array $engine_info
 *   Engine type declaration.
 * @param string $default
 *   Preferred engine.
 *
 * @return string
 *   Selected engine.
 */
function drush_select_engine($config, $engine_info, $preferred = NULL) {
  $engines = array_keys($engine_info['engines']);

  if (in_array($preferred, $engines)) {
    return $preferred;
  }

  if (!empty($config['option'])) {
    $engine = drush_get_option($config['option'], FALSE);
    if ($engine && in_array($engine, $engines)) {
      return $engine;
    }
  }

  if (isset($config['default']) && in_array($config['default'], $engines)) {
    return $config['default'];
  }

  return current($engines);
}

/**
 * Loads and validate an engine of the given type.
 *
 * @param string $type
 *   Engine type.
 * @param string $engine
 *   Engine name.
 * @param array $config
 *   Engine configuration. Tipically it comes from a command declaration.
 *
 * @return
 *   TRUE or instanced object of available class on success. FALSE on fail.
 */
function drush_load_engine($type, $engine, $config = array()) {
  $engine_info = drush_get_engines($type);
  $engine = drush_select_engine($config, $engine_info, $engine);
  $config['engine-info'] = $engine_info['engines'][$engine];

  // Check engine dependency on drupal modules before include.
  $dependencies = $config['engine-info']['drupal dependencies'];
  foreach ($dependencies as $dependency) {
    if (!drush_module_exists($dependency)) {
      return drush_set_error('DRUSH_ENGINE_DEPENDENCY_ERROR', dt('!engine_type: !engine engine needs the following modules installed/enabled to run: !dependencies.', array('!engine_type' => $type, '!engine' => $engine, '!dependencies' => implode(', ', $dependencies))));
    }
  }

  $result = drush_include_engine($type, $engine, $config);
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
 * If a class named in the form drush_$type_$engine exists, it will return an
 * instance of the class.
 *
 * @param string $type
 *   The type of engine.
 * @param string $engine
 *   The name for the engine to include.
 * @param array $config
 *   Parameters for the engine class constructor.
 *
 * @return
 *   TRUE or instanced object of available class on success. FALSE on fail.
 */
function drush_include_engine($type, $engine, $config = NULL) {
  $engine_info = drush_get_engines($type);

  // Pick the engine name that actually implements the requested engine.
  $engine = isset($engine_info['engines'][$engine]['implemented-by']) ? $engine_info['engines'][$engine]['implemented-by'] : $engine;

  // Legacy engines live in a subdirectory of the commandfile
  // that declares them. We need to explicitly include the file.
  if (isset($engine_info['engines'][$engine]['path'])) {
    $path = $engine_info['engines'][$engine]['path'];
    if (!drush_include($path, $engine)) {
      return drush_set_error('DRUSH_ENGINE_INCLUDE_FAILED', dt('Unable to include the !type engine !engine from !path.' , array('!path' => $path, '!type' => $type, '!engine' => $engine)));
    }
    // Legacy engines may be implemented in a magic class name.
    $class = 'drush_' . $type . '_' . str_replace('-', '_', $engine);
    if (class_exists($class)) {
      $instance = new $class($config);
      $instance->engine_type = $type;
      $instance->engine = $engine;
      return $instance;
    }

    return TRUE;
  }

  return drush_get_class($engine_info['engines'][$engine]['class'], array($type, $engine, $config));
}

/**
 * Return the engine of the specified type that was loaded by the Drush command.
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
