<?php

/**
 * @file
 * This file is only used by Drush8. Drush9 discovers its commands via tagged
 * service(s) in devel.services.yml. Also see classes in src/Commands.
 */

use Drupal\Component\Uuid\Php;

/**
 * Implements hook_drush_command().
 */
function devel_drush_command() {
  $items['devel-reinstall'] = array(
    'description' => dt('Uninstall, and Install a list of projects.'),
    'drush dependencies' => array('pm'),
    'arguments' => array(
      'projects' => dt('A space-separated list of project names.'),
    ),
    'allow-additional-options' => array('pm-uninstall', 'pm-enable'),
    'required-arguments' => 1,
    'aliases' => array('dre'),
  );
  $items['fn-hook'] = array(
    'description' => 'List implementations of a given hook and explore the source of the selected one.',
    'arguments' => array(
      'hook' => 'The name of the hook to explore (e.g. "menu" for hook_menu()).'
    ),
    'examples' => array(
      'fn-hook cron' => 'List implementations of hook_cron().',
    ),
    'allow-additional-options' => array('fn-view'),
    'required-arguments' => 1,
    'aliases' => array('fnh', 'hook'),
  );
  $items['fn-event'] = array(
    'description' => 'List implementations of a given event and explore source of specified one.',
    'arguments' => array(
      'event' => 'The name of the event to explore. If omitted, a list of events is shown.'
    ),
    'examples' => array(
      'fn-event' => 'Pick a Kernel event, then pick an implementation, and then view its source code.',
      'fn-event kernel.terminate' => 'Pick a terminate subscribers and view its source code.',
    ),
    'allow-additional-options' => array('fn-view'),
    'aliases' => array('fne', 'event'),
  );
  $items['fn-view'] = array(
    'description' => 'Show the source of specified function or method.',
    'arguments' => array(
      'function' => 'The name of the function or method to view.',
    ),
    'options' => array(
      'pipe' => 'Output just the filename of the function or method',
      'format' => 'Specify how the filename should be printed. Available placeholders are @startline, @endline and @file',
    ),
    'examples' => array(
      'fn-view drupal_set_breadcrumb' => 'View the source code for function "drupal_set_breadcrumb"',
      'vi `drush --pipe fn-view user_access --format=\'+@startline @file\'`' => 'Edit the file that contains the function "user_access"',
      'fn-view NodeController::load' => 'View the source code for method load in the class NodeController'
    ),
    'aliases' => array('fnv'),
    'required-arguments' => 1,
  );
  $items['devel-token'] = array(
    'description' => dt('List available tokens'),
    'aliases' => array('token'),
    //@todo support --format option for json, csv, etc.
  );

  $items['devel-container-services'] = array(
    'description' => 'Get a list of available container services.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'core' => array('8+'),
    'aliases' => array('dcs'),
    'options' => array(
      'format' => 'Format to output. Allowed values are: json, export, html.',
    ),
    'arguments' => array(
      'prefix' => 'A prefix to filter the service list by.',
    ),
    'examples' => array(
      'drush container-services' => 'Gets a list of all available container services',
      'drush container-services plugin.manager' => 'Get all services containing "plugin.manager"',
    ),
    'outputformat' => array(
      'default' => 'list',
      'pipe-format' => 'export',
    ),
  );

  $items['devel-generate-uuid'] = array(
    'description' => 'Generate a UUID.',
    'core' => array('8+'),
    'examples' => array(
      "drush devel-generate-uuid" => "Outputs a Universally Unique IDentifier.",
    ),
    'aliases' => array('uuid'),
    'outputformat' => array(
      'default' => 'string',
    ),
  );

  return $items;
}

/**
 * A command callback. This is faster than 3 separate bootstraps.
 */
function drush_devel_reinstall() {
  $projects = func_get_args();

  $args = array_merge(array('pm-uninstall'), $projects);
  call_user_func_array('drush_invoke', $args);

  $args = array_merge(array('pm-enable'), $projects);
  call_user_func_array('drush_invoke', $args);
}

/**
 * Command handler. Show hook implementations.
 */
function drush_devel_fn_hook($hook) {
  // Get implementations in the .install files as well.
  include_once './core/includes/install.inc';
  drupal_load_updates();

  if ($hook_implementations = \Drupal::moduleHandler()->getImplementations($hook)) {
    if ($choice = drush_choice(array_combine($hook_implementations, $hook_implementations), 'Enter the number of the hook implementation you wish to view.')) {
      return drush_devel_fn_view($choice . "_$hook");
    }
  }
  else {
    drush_log(dt('No implementations.'), 'ok');
  }
}

/**
 * Command handler. Show hook implementations.
 */
function drush_devel_fn_event($event = NULL) {
  $dispatcher = Drupal::service('event_dispatcher');
  if (empty($event)) {
    $events = array('kernel.controller', 'kernel.exception', 'kernel.request', 'kernel.response', 'kernel.terminate', 'kernel.view');
    $events = array_combine($events, $events);
    if (!$event = drush_choice($events, 'Enter the event you wish to explore.')) {
      return drush_user_abort();
    }
  }
  if ($implementations = $dispatcher->getListeners($event)) {
    foreach ($implementations as $implementation) {
      $callable = get_class($implementation[0]) . '::' . $implementation[1];
      $choices[$callable] = $callable;
    }
    if ($choice = drush_choice($choices, 'Enter the number of the implementation you wish to view.')) {
      return drush_devel_fn_view($choice);
    }
  }
  else {
    drush_log(dt('No implementations.'), 'ok');
  }
}

/**
 * Command handler.  Show source code of specified function or method.
 */
function drush_devel_fn_view($function_name) {
  // Get implementations in the .install files as well.
  include_once './core/includes/install.inc';
  drupal_load_updates();

  if (strpos($function_name, '::') === FALSE) {
    if (!function_exists($function_name)) {
      return drush_set_error(dt('Function not found'));
    }
    $reflect = new ReflectionFunction($function_name);
  }
  else {
    list($class, $method) = explode('::', $function_name);
    if (!method_exists($class, $method)) {
      return drush_set_error(dt('Method not found'));
    }
    $reflect = new ReflectionMethod($class, $method);
  }
  $func_info = array('@file' => $reflect->getFileName(), '@startline' => $reflect->getStartLine(), '@endline' => $reflect->getEndLine());
  $format = drush_get_option('format', '@file');
  drush_print_pipe(dt($format, $func_info));
  drush_print(dt("// file: @file, lines @startline-@endline", $func_info));

  _drush_devel_print_function($reflect->getFileName(), $reflect->getStartLine(), $reflect->getEndLine());
}

/**
 * Command callback. List available tokens.
 */
function drush_devel_token() {
  $rows[] = array(dt('Group'), dt('Token'), dt('Name'));
  $all = \Drupal::token()->getInfo();
  foreach ($all['tokens'] as $group => $tokens) {
    foreach ($tokens as $key => $token) {
      $rows[] = array($group, $key, $token['name']);
    }
  }
  drush_print_table($rows, TRUE);
}

/**
 * Command callback. Outputs a UUID.
 *
 * @return string
 *   A freshly generated UUID.
 */
function drush_devel_generate_uuid() {
  $uuid = new Php();
  return $uuid->generate();
}

/**
 * Print the specified function, including any
 * doxygen-style comments that come before it.
 */
function _drush_devel_print_function($file, $start_line, $end_line) {
  $line_num = 0;
  $doxygen = NULL;
  $fp = fopen( $file, 'r' );

  while (!feof($fp) && ($line_num < ($start_line - 1))) {
    $line = fgets($fp);
    ++$line_num;

    if (substr($line,0,3) == '/**') {
      $doxygen = $line;
    }
    elseif (isset($doxygen)) {
      $doxygen .= $line;
      if ($line_num + 1 == $start_line) {
        drush_print(rtrim($doxygen));
      }
      if (strstr($line, '*/') !== FALSE) {
        $doxygen = NULL;
      }
    }
  }
  while (!feof($fp) && ($line_num < $end_line)) {
    $line = fgets($fp);
    ++$line_num;
    drush_print(rtrim($line));
  }
}

/**
 * Command callback to list available container services.
 */
function drush_devel_container_services($prefix = NULL) {
  $container = Drupal::getContainer();

  if (empty($container)) {
    return drush_set_error(dt('No container was found.'));
  }

  // Get a list of all available service IDs.
  $services = $container->getServiceIds();

  // If there is a prefix, try to find matches.
  if (isset($prefix)) {
    $services = preg_grep("/$prefix/", $services);
  }

  if (empty($services)) {
    return drush_log(dt('No container services found.'), 'ok');
  }

  sort($services);
  return $services;
}
