<?php

// Get a $_SERVER key, or equivalent environment variable
// if it is not set in $_SERVER.
function runserver_env($key) {
  if (isset($_SERVER[$key])) {
    return $_SERVER[$key];
  }
  else {
    return getenv($key);
  }
}

$url = parse_url($_SERVER["REQUEST_URI"]);
if (file_exists('.' . $url['path'])) {
  // Serve the requested resource as-is.
  return FALSE;
}

// Populate the "q" query key with the path, skip the leading slash.
$_GET['q'] = $_REQUEST['q'] = substr($url['path'], 1);

$_SERVER['SCRIPT_NAME'] = '/index.php';

// Include the main index.php and let core take over.
// n.b. Drush sets the cwd to the Drupal root during bootstrap.
include 'index.php';
