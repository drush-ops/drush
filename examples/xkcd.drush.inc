<?php

/**
 * @file
 *   Example drush command.
 *
 *   To run this *fun* command, execute `drush --include=./examples xkcd`
 *   from within your drush directory.
 *
 *   See `drush topic docs-commands` for more information about command authoring.
 *
 *   You can copy this file to any of the following
 *     1. A .drush folder in your HOME folder.
 *     2. Anywhere in a folder tree below an active module on your site.
 *     3. /usr/share/drush/commands (configurable)
 *     4. In an arbitrary folder specified with the --include option.
 *     5. Drupal's /drush or /sites/all/drush folders.
 */

/**
 * Implementation of hook_drush_command().
 *
 * In this hook, you specify which commands your
 * drush module makes available, what it does and
 * description.
 *
 * Notice how this structure closely resembles how
 * you define menu hooks.
 *
 * See `drush topic docs-commands` for a list of recognized keys.
 *
 * @return
 *   An associative array describing your command(s).
 */
function xkcd_drush_command() {
  $items = array();

  // The 'xkcd' command
  $items['xkcd-fetch'] = array(
    'description' => "Retrieve and display xkcd cartoons.",
    'arguments' => array(
      'search' => 'Optional argument to retrive the cartoons matching an index number, keyword search or "random". If omitted the latest cartoon will be retrieved.',
    ),
    'options' => array(
      'image-viewer' => 'Command to use to view images (e.g. xv, firefox). Defaults to "display" (from ImageMagick).',
      'google-custom-search-api-key' => 'Google Custom Search API Key, available from https://code.google.com/apis/console/. Default key limited to 100 queries/day globally.',
    ),
    'examples' => array(
      'drush xkcd' => 'Retrieve and display the latest cartoon.',
      'drush xkcd sandwich' => 'Retrieve and display cartoons about sandwiches.',
      'drush xkcd 123 --image-viewer=eog' => 'Retrieve and display cartoon #123 in eog.',
      'drush xkcd random --image-viewer=firefox' => 'Retrieve and display a random cartoon in Firefox.',
    ),
    'aliases' => array('xkcd'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 *
 * This function is called whenever a drush user calls
 * 'drush help <name-of-your-command>'. This hook is optional. If a command
 * does not implement this hook, the command's description is used instead.
 *
 * This hook is also used to look up help metadata, such as help
 * category title and summary.  See the comments below for a description.
 *
 * @param
 *   A string with the help section (prepend with 'drush:')
 *
 * @return
 *   A string with the help text for your command.
 */
function xkcd_drush_help($section) {
  switch ($section) {
    case 'drush:xkcd-fetch':
      return dt("A command line tool (1) for a web site tool (2), that emulates
(badly) a web based tool (3) that emulates (badly) a command line tool (4) to
access a web site (5) with awesome geek humor.\n
(1) Drush
(2) Drupal
(3) http://uni.xkcd.com/
(4) BASH
(5) http://xkcd.com/");
  }
}

/**
 * Example drush command callback. This is where the action takes place.
 *
 * The function name should be same as command name but with dashes turned to
 * underscores and 'drush_commandfile_' prepended, where 'commandfile' is
 * taken from the file 'commandfile.drush.inc', which in this case is 'sandwich'.
 * Note also that a simplification step is also done in instances where
 * the commandfile name is the same as the beginning of the command name,
 * "drush_example_example_foo" is simplified to just "drush_example_foo".
 * To also implement a hook that is called before your command, implement
 * "drush_hook_pre_example_foo".  For a list of all available hooks for a
 * given command, run drush in --debug mode.
 *
 * If for some reason you do not want your hook function to be named
 * after your command, you may define a 'callback' item in your command
 * object that specifies the exact name of the function that should be
 * called.
 *
 * In this function, all of Drupal's API is (usually) available, including
 * any functions you have added in your own modules/themes.
 *
 * @see drush_invoke()
 * @see drush.api.php
 *
 * @param
 *   An optional string with search keyworks, cartoon ID or "random".
 */
function drush_xkcd_fetch($search = '') {
  if (empty($search)) {
    drush_xkcd_display('http://xkcd.com');
  }
  elseif (is_numeric($search)) {
    drush_xkcd_display('http://xkcd.com/' . $search);
  }
  elseif ($search == 'random') {
    $xkcd_response = @json_decode(file_get_contents('http://xkcd.com/info.0.json'));
    if (!empty($xkcd_response->num)) {
      drush_xkcd_display('http://xkcd.com/' . rand(1, $xkcd_response->num));
    }
  }
  else {
    // This uses an API key with a limited number of searches per
    $search_response = @json_decode(file_get_contents('https://www.googleapis.com/customsearch/v1?key=' . drush_get_option('google-custom-search-api-key', 'AIzaSyDpE01VDNNT73s6CEeJRdSg5jukoG244ek') . '&cx=012652707207066138651:zudjtuwe28q&q=' . $search));
    if (!empty($search_response->items)) {
      foreach ($search_response->items as $item) {
        drush_xkcd_display($item->link);
      }
    }
    else {
      drush_set_error('DRUSH_XKCD_SEARCH_FAIL', dt('The search failed or produced no results.'));
    }
  }
}

/**
 * Retrieve and display a table of metadata for an XKCD cartoon,
 * then retrieve and display the cartoon using a specified image viewer.
 *
 * @param
 *   A string with the URL of the cartoon to display.
 */
function drush_xkcd_display($url) {
  $xkcd_response = @json_decode(file_get_contents($url . '/info.0.json'));
  if (!empty($xkcd_response->num)) {
    $data = (array)$xkcd_response;
    $data['date'] = $data['year'] . '/' . $data['month'] . '/' . $data['day'];
    unset($data['safe_title'], $data['news'], $data['link'], $data['year'], $data['month'], $data['day']);
    drush_print_table(drush_key_value_to_array_table($data));
    $img = drush_download_file($data['img']);
    drush_register_file_for_deletion($img);
    drush_shell_exec(drush_get_option('image-viewer', 'display') . ' ' . $img);
  }
  else {
    drush_set_error('DRUSH_XKCD_METADATA_FAIL', dt('Unable to retrieve cartoon metadata.'));
  }
}
