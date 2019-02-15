<?php

$url = parse_url($_SERVER["REQUEST_URI"]);
if (file_exists('.' . urldecode($url['path']))) {
  // Serve the requested resource as-is.
  return FALSE;
}

// The built in webserver incorrectly sets $_SERVER['SCRIPT_NAME'] when URLs
// contain multiple dots (such as config entity IDs) in the path. Since this is
// a virtual resource, served by index.php set the script name explicitly.
// See https://github.com/drush-ops/drush/issues/2033 for more information.
// Work around the PHP bug. Update $_SERVER variables to point to the correct
// index-file.
$path = $url['path'];
$script = 'index.php';
if (strpos($path, '.php') !== FALSE) {
  // Work backwards through the path to check if a script exists. Otherwise
  // fallback to index.php.
  do {
    $path = dirname($path);
    if (preg_match('/\.php$/', $path) && is_file('.' . $path)) {
      // Discovered that the path contains an existing PHP file. Use that as the
      // script to include.
      $script = ltrim($path, '/');
      break;
    }
  } while ($path !== '/' && $path !== '.');
}

// Update $_SERVER variables to point to the correct index-file.
$index_file_absolute = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $script;
$index_file_relative = DIRECTORY_SEPARATOR . $script;

// SCRIPT_FILENAME will point to the router script itself, it should point to
// the full path of index.php.
$_SERVER['SCRIPT_FILENAME'] = $index_file_absolute;

// SCRIPT_NAME and PHP_SELF will either point to index.php or contain the full
// virtual path being requested depending on the URL being requested. They
// should always point to index.php relative to document root.
$_SERVER['SCRIPT_NAME'] = $index_file_relative;
$_SERVER['PHP_SELF'] = $index_file_relative;

// Require the script and let core take over.
require $_SERVER['SCRIPT_FILENAME'];