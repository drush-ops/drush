<?php

/**
 * @file
 * Drush integration for views.
 */

use Drupal\views\Analyzer;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use Drupal\Component\Utility\MapArray;

/**
 * Implements hook_drush_help().
 */
function views_drush_help($section) {
  switch ($section) {
    case 'meta:views:title':
      return dt('Views commands');
    case 'meta:views:summary':
      return dt('Views drush commands.');
  }
}

/**
 * Implements hook_drush_command().
 */
function views_drush_command() {
  $items = array();

  $base = array(
    'core' => array('8+'),
    'drupal dependencies' => array('views'),
  );

  $items['views-dev'] = array(
    'callback' => 'views_development_settings',
    'description' => 'Set the Views settings to more developer-oriented values.',
    'aliases' => array('vd'),
  ) + $base;

  $items['views-list'] = array(
    'description' => 'Get a list of all views in the system.',
    'aliases' => array('vl'),
    'options' => array(
      'name' => array(
        'description' => 'A string contained in the view\'s name to filter the results with.',
        'example-value' => 'node',
        'value' => 'required',
      ),
      'tags' => array(
        'description' => 'A comma-separated list of views tags by which to filter the results.',
        'example-value' => 'default',
        'value' => 'required',
      ),
      'status' => array(
        'description' => 'Status of the views by which to filter the results. Choices: enabled, disabled.',
        'example-value' => 'enabled',
        'value' => 'required',
      ),
      'format' => array(
        'description' => 'Define the output format. Known formats are: json, print_r, and export.',
      ),
    ),
    'examples' => array(
      'drush vl' => 'Show a list of all available views.',
      'drush vl --name=blog' => 'Show a list of views which names contain "blog".',
      'drush vl --tags=tag1,tag2' => 'Show a list of views tagged with "tag1" or "tag2".',
      'drush vl --status=enabled' => 'Show a list of enabled views.',
    ),
  ) + $base;

  $items['views-execute'] = array(
    'description' => 'Execute a view and get the results.',
    'aliases' => array('vex'),
    'arguments' => array(
      'view' => 'The name of the view to execute.',
      'display' => 'The display ID to execute. If none specified, the default display will be used.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'count' => array(
        'description' => 'Display a count of the results instead of each row.',
      ),
      'rendered' => array(
        'description' => 'Return the results as rendered HTML output for the display.',
      ),
      'show-admin-links' => array(
        'description' => 'Show contextual admin links in the rendered markup.',
      ),
    ),
    'outputformat' => array(
      'default' => 'print-r',
      'pipe-format' => 'var_export',
    ),
    'examples' => array(
      'drush views-execute my_view' => 'Show the result set of the default display for the my_view view.',
      'drush views-execute my_view page_1 --rendered' => 'Show the rendered output of the my_view:page_1 view.',
      'drush views-execute my_view page_1 3 --count' => 'Show a count of my_view:page_1 with an agument of 3 being passed.',
    ),
  ) + $base;

  $items['views-analyze'] = array(
    'drupal dependencies' => array('views', 'views_ui'),
    'description' => 'Get a list of all Views analyze warnings',
    'aliases' => array('va'),
    'options' => array(
      'format' => array(
        'description' => 'Define the output format. Known formats are: json, print_r, and export.',
      ),
    ),
  ) + $base;

  $items['views-enable'] = array(
    'description' => 'Enable the specified views.',
    'arguments' => array(
      'views' => 'A space delimited list of view names.',
    ),
    'required-arguments' => 1,
    'aliases' => array('ven'),
    'examples' => array(
      'drush ven frontpage taxonomy_term' => 'Enable the frontpage and taxonomy_term views.',
    ),
  ) + $base;

  $items['views-disable'] = array(
    'description' => 'Disable the specified views.',
    'arguments' => array(
      'views' => 'A space delimited list of view names.',
    ),
    'required-arguments' => 1,
    'aliases' => array('vdis'),
    'examples' => array(
      'drush vdis frontpage taxonomy_term' => 'Disable the frontpage and taxonomy_term views.',
    ),
  ) + $base;

  return $items;
}

/**
 * Drush views dev command.
 *
 * Changes the settings to more developer oriented values.
 */
function views_development_settings() {
  $settings = array(
    'ui.show.listing_filters' => TRUE,
    'ui.show.master_display' => TRUE,
    'ui.show.advanced_column' => TRUE,
    'ui.always_live_preview' => FALSE,
    'ui.always_live_preview_button' => TRUE,
    'ui.show.preview_information' => TRUE,
    'ui.show.sql_query.enabled' => TRUE,
    'ui.show.sql_query.where' => 'above',
    'ui.show.performance_statistics' => TRUE,
    'ui.show.additional_queries' => TRUE,
    'debug.output' => TRUE,
    'debug.region' => 'message',
    'ui.show.display_embed' => TRUE,
  );

  $config = config('views.settings');

  foreach ($settings as $setting => $value) {
    $config->set($setting, $value);
    // Convert boolean values into a string to print.
    if (is_bool($value)) {
      $value = $value ? 'TRUE' : 'FALSE';
    }
    // Wrap string values in quotes.
    elseif (is_string($value)) {
      $value = "\"$value\"";
    }
    drush_log(dt('!setting set to !value', array('!setting' => $setting, '!value' => $value)));
  }

  // Save the new config.
  $config->save();

  drush_log(dt('New views configuration saved.'), 'success');
}

/**
 * Callback function for views-list command.
 */
function drush_views_list() {
  $disabled_views = array();
  $enabled_views = array();

  $format = drush_get_option('format', FALSE);

  $views = Drupal::entityManager()->getStorageController('view')->loadMultiple();

  // Get the --name option.
  $name = array_filter(drush_get_option_list('name'));
  $with_name = !empty($name) ? TRUE : FALSE;

  // Get the --tags option.
  $tags = array_filter(drush_get_option_list('tags'));
  $with_tags = !empty($tags) ? TRUE : FALSE;

  // Get the --status option. Store user input appart to reuse it after.
  $status = drush_get_option('status', FALSE);

  // Throw an error if it's an invalid status.
  if ($status && !in_array($status, array('enabled', 'disabled'))) {
    return drush_set_error(dt('Invalid status: @status. Available options are "enabled" or "disabled"', array('@status' => $status)));
  }

  // Set the table headers.
  $header = array(
    dt('Machine name'),
    dt('Human name'),
    dt('Description'),
    dt('Status'),
    dt('Tag'),
  );

  // Setup a row for each view.
  foreach ($views as $view) {
    // If options were specified, check that first mismatch push the loop to the
    // next view.
    if ($with_name && !stristr($view->id(), $name[0])) {
      continue;
    }
    if ($with_tags && !in_array($view->get('tag'), $tags)) {
      continue;
    }

    $status_bool = $status == 'enabled';
    if ($status && ($view->status() !== $status_bool)) {
      continue;
    }

    $row = array();

    $row[] = $view->id();
    $row[] = $view->label();
    $row[] = $view->get('description');
    $row[] = $view->status() ? dt('Enabled') : dt('Disabled');
    $row[] = $view->get('tag');

    // Place the row in the appropiate array, so we can have disabled views at
    // the bottom.
    if ($view->status()) {
      $enabled_views[] = $row;
      }
    else{
      $disabled_views[] = $row;
    }

    unset($row);
  }

  $disabled = count($disabled_views);

  // Sort alphabeticaly.
  asort($disabled_views);
  asort($enabled_views);

  // If options were used.
  $summary = '';
  if ($with_name || $with_tags || $status) {
    $summary = dt('Views');

    if ($with_name) {
      $summary .= ' ' . dt('named "@name"', array('@name' => $name[0]));
    }

    if ($with_tags) {
      $tags = implode(' or ', $tags);
      $summary .= ' ' . dt('tagged with "@tags"', array('@tags' => $tags));
    }

    if ($status) {
      $summary .= ' ' . dt('with a status of "@status"', array('@status' => $status));
    }

    drush_print($summary . ":\n");
  }

  // Return all rows or print as a table.
  if (count($enabled_views) || count($disabled_views)) {
    $rows = array_merge($enabled_views, $disabled_views);

    if ($format) {
      $output = drush_format($rows, $format);
      drush_print($output);
      return $output;
    }
    else {
      $total = count($rows);
      // Put the headers as first row.
      array_unshift($rows, $header);

      drush_print_table($rows, TRUE);

      // Print the summary messages.
      drush_print(dt('A total of @total views were found in this Drupal installation:', array('@total' => $total)));
      drush_print('  ' . dt('@dis views are disabled', array('@dis' => $disabled)) . "\n");

      return $rows;
    }
  }
  else {
    drush_set_error(dt('No views found.'));
  }

}

/**
 * Drush views execute command.
 */
function drush_views_execute($view_name, $display_id = NULL) {
  $args = func_get_args();
  $view_args = array();

  // If it's more than 2, we have arguments. A display has to be specified in
  // that case.
  if (count($args) > 2) {
    $view_args = array_slice($args, 2);
  }

  if (!$view = Views::getView($view_name)) {
    return drush_set_error(dt('View: "@view" not found.', array('@view' => $view_name)));
  }

  // Set the display and execute the view.
  $view->setDisplay($display_id);
  $view->preExecute($view_args);
  $view->execute();

  if (drush_get_option('count', FALSE)) {
    return count($view->result);
  }
  elseif (!empty($view->result)) {
    if (drush_get_option('rendered', FALSE)) {
      // Don't show admin links in markup by default.
      $view->hide_admin_links = !drush_get_option('show-admin-links', FALSE);
      return $view->preview();
    }
    else {
      return $view->result;
    }
  }
  else {
    drush_log(dt('No results returned for this view.') ,'warning');
    return NULL;
  }
}

/**
 * Drush views analyze command.
 */
function drush_views_analyze() {
  $messages = NULL;
  $messages_count = 0;

  $format = drush_get_option('format', FALSE);

  $views = Drupal::entityManager()->getStorageController('view')->loadMultiple();

  if (!empty($views)) {
    $analyzer = Drupal::service('views.analyzer');
    foreach ($views as $view_name => $view) {
      $view = $view->getExecutable();

      if ($messages = $analyzer->getMessages($view)) {
        if ($format) {
          $output = drush_format($messages, $format);
          drush_print($output);
          return $output;
        }
        else {
          drush_print($view_name);
          foreach ($messages as $message) {
            $messages_count++;
            drush_print($message['type'] .': '. $message['message'], 2);
          }
        }
      }
    }

    drush_log(dt('A total of @total views were analyzed and @messages problems were found.', array('@total' => count($views), '@messages' => $messages_count)), 'ok');
    return $messages;
  }
  else {
    return drush_set_error(dt('There are no views to analyze'));
  }
}

/**
 * Drush views enable command.
 */
function drush_views_enable() {
  $view_names = func_get_args();
  _views_drush_op('enable', $view_names);
}

/**
 * Drush views disable command.
 */
function drush_views_disable() {
  $view_names = func_get_args();
  _views_drush_op('disable', $view_names);
}

/**
 * Perform operations on view objects.
 *
 * @param string $op
 *   The operation to perform.
 * @param array $view_names
 *   An array of view names to load and perform this operation on.
 */
function _views_drush_op($op = '', array $view_names = array()) {
  $op_types = _views_drush_op_types();
  if (!in_array($op, array_keys($op_types))) {
    return drush_set_error(dt('Invalid op type'));
  }

  $view_names = MapArray::copyValuesToKeys($view_names);

  if ($views = Drupal::entityManager()->getStorageController('view')->loadMultiple($view_names)) {
    foreach ($views as $view) {
      $tokens = array('@view' => $view->id(), '@action' => $op_types[$op]['action']);

      if ($op_types[$op]['validate']($view)) {
        $function = 'views_' . $op . '_view';
        drush_op($function, $view);
        drush_log(dt('View: @view has been @action', $tokens), 'success');
      }
      else {
        drush_log(dt('View: @view is already @action', $tokens), 'notice');
      }
      // Remove this view from the viewnames input list.
      unset($view_names[$view->id()]);
    }

    return $views;
  }
  else {
    drush_set_error(dt('No views have been loaded'));
  }

  // If we have some unmatched/leftover view names that weren't loaded.
  if (!empty($view_names)) {
    foreach ($view_names as $viewname) {
      drush_log(dt('View: @view could not be found.', array('@view' => $viewname)), 'error');
    }
  }

}

/**
 * Returns an array of op types that can be performed on views.
 *
 * @return array
 *   An associative array keyed by op type => action name.
 */
function _views_drush_op_types() {
  return array(
    'enable' => array(
      'action' => dt('enabled'),
      'validate' => '_views_drush_view_is_disabled',
    ),
    'disable' => array(
      'action' => dt('disabled'),
      'validate' => '_views_drush_view_is_enabled',
    ),
  );
}

/**
 * Returns whether a view is enabled.
 *
 * @param Drupal\views\Entity\ViewDrupal\views\ $view
 *   The view object to check.
 *
 * @return bool
 *   TRUE if the View is enabled, FALSE otherwise.
 */
function _views_drush_view_is_enabled(View $view) {
  return $view->status();
}

/**
 * Returns whether a view is disabled.
 *
 * @param Drupal\views\Entity\View $view
 *   The view object to check.
 *
 * @return bool
 *   TRUE if the View is disabled, FALSE otherwise.
 */
function _views_drush_view_is_disabled(View $view) {
  return !$view->status();
}

/**
 * Adds a cache clear option for views.
 */
function views_drush_cache_clear(&$types) {
  $types['views'] = 'views_invalidate_cache';
}

/**
 * Command argument complete callback.
 */
function drush_views_enable_complete() {
  return _drush_views_complete();
}

/**
 * Command argument complete callback.
 */
function drush_views_disable_complete() {
  return _drush_views_complete();
}

/**
 * Helper function to return a list of view names for complete callbacks.
 *
 * @return array
 *   An array of available view names.
 */
function _drush_views_complete() {
  return array('values' => array_keys(Drupal::entityManager()->getStorageController('view')->loadMultiple()));
}
