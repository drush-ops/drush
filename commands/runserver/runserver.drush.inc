<?php

/**
 * @file
 * Built in http server commands.
 */

/**
 * Implements hook_drush_help().
 */
function runserver_drush_help($section) {
  switch ($section) {
    case 'meta:runserver:title':
      return dt("Runserver commands");
    case 'meta:runserver:summary':
      return dt('Launch the built-in PHP webserver.');
    case 'drush:runserver':
      return dt("Runs a lightweight built in http server for development.
 - Don't use this for production, it is neither scalable nor secure for this use.
 - If you run multiple servers simultaneously, you will need to assign each a unique port.
 - Use Ctrl-C or equivalent to stop the server when complete.");
  }
}

/**
 * Implements hook_drush_command().
 */
function runserver_drush_command() {
  $items = array();

  $items['runserver'] = array(
    'description' => 'Runs PHP\'s built-in http server for development.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'arguments' => array(
      'addr:port/path' => 'Host IP address and port number to bind to and path to open in web browser. Format is addr:port/path, default 127.0.0.1:8888, all elements optional. See examples for shorthand. Only opens a browser if a path is specified.',
    ),
    'options' => array(
      'variables' => 'Key-value array of variables to override in the $conf array for the running site. By default disables drupal_http_request_fails to avoid errors on Windows (which supports only one connection at a time). Comma delimited list of name=value pairs (or array in drushrc).',
      'default-server' => 'A default addr:port/path to use for any values not specified as an argument.',
      'user' => 'If opening a web browser, automatically log in as this user (user ID or username). Default is to log in as uid 1.',
      'browser' => 'If opening a web browser, which browser to user (defaults to operating system default). Use --no-browser to avoid opening a browser.',
      'dns' => 'Resolve hostnames/IPs using DNS/rDNS (if possible) to determine binding IPs and/or human friendly hostnames for URLs and browser.',
    ),
    'aliases' => array('rs'),
    'examples' => array(
      'drush rs 8080' => 'Start runserver on 127.0.0.1, port 8080.',
      'drush rs 10.0.0.28:80' => 'Start runserver on 10.0.0.28, port 80.',
      'drush rs [::1]:80' => 'Start runserver on IPv6 localhost ::1, port 80.',
      'drush rs --dns localhost:8888/user' => 'Start runserver on localhost (using rDNS to determine binding IP), port 8888, and open /user in browser.',
      'drush rs /' => 'Start runserver on default IP/port (127.0.0.1, port 8888), and open / in browser.',
      'drush rs --default-server=127.0.0.1:8080/ -' => 'Use a default (would be specified in your drushrc) that starts runserver on port 8080, and opens a browser to the front page. Set path to a single hyphen path in argument to prevent opening browser for this session.',
      'drush rs :9000/admin' => 'Start runserver on 127.0.0.1, port 9000, and open /admin in browser. Note that you need a colon when you specify port and path, but no IP.',
    ),
  );
  return $items;
}

/**
 * Callback for runserver command.
 */
function drush_core_runserver($uri = NULL) {
  global $user, $base_url;

  // Determine active configuration.
  $uri = runserver_uri($uri);
  if (!$uri) {
    return FALSE;
  }

  // Remove any leading slashes from the path, since that is what url() expects.
  $path = ltrim($uri['path'], '/');

  // $uri['addr'] is a special field set by runserver_uri()
  $hostname = $uri['host'];
  $addr = $uri['addr'];

  drush_set_context('DRUSH_URI', 'http://' . $hostname . ':' . $uri['port']);

  // We pass in the currently logged in user (if set via the --user option),
  // which will automatically log this user in the browser during the first
  // request.
  if (drush_get_option('user', FALSE) === FALSE) {
    drush_set_option('user', 1);
  }
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_LOGIN);

  // We delete any registered files here, since they are not caught by Ctrl-C.
  _drush_delete_registered_files();

  // We set the effective base_url, since we have now detected the current site,
  // and need to ensure generated URLs point to our runserver host.
  // We also pass in the effective base_url to our auto_prepend_script via the
  // CGI environment. This allows Drupal to generate working URLs to this http
  // server, whilst finding the correct multisite from the HTTP_HOST header.
  $base_url = 'http://' . $addr . ':' . $uri['port'];
  $env['RUNSERVER_BASE_URL'] = $base_url;

  // We pass in an array of $conf overrides using the same approach.
  // This is available as an option for developers to pass in their own
  // favorite $conf overrides (e.g. disabling css aggregation).
  $current_override = drush_get_option_list('variables', array());
  $override = array();
  foreach ($current_override as $name => $value) {
    if (is_numeric($name) && (strpos($value, '=') !== FALSE)) {
      list($name, $value) = explode('=', $value, 2);
    }
    $override[$name] = $value;
  }
  $env['RUNSERVER_CONF'] = urlencode(serialize($override));

  // We log in with the specified user ID (if set) via the password reset URL.
  $user_message = '';
  $usersingle = drush_user_get_class()->getCurrentUserAsSingle();
  if ($usersingle->id()) {
    $browse = $usersingle->passResetUrl($path);
    $user_message = ', logged in as ' . $usersingle->getUsername();
  }
  else {
    $browse = drush_url($path);
  }

  drush_print(dt('HTTP server listening on !addr, port !port (see http://!hostname:!port/!path), serving site !site!user...', array('!addr' => $addr, '!hostname' => $hostname, '!port' => $uri['port'], '!path' => $path, '!site' => drush_get_context('DRUSH_DRUPAL_SITE', 'default'), '!user' => $user_message)));
  // Start php 5.4 builtin server.
  // Store data used by runserver-prepend.php in the shell environment.
  foreach ($env as $key => $value) {
    putenv($key . '=' . $value);
  }
  if (!empty($uri['path'])) {
    // Start a browser if desired. Include a 2 second delay to allow the
    // server to come up.
    drush_start_browser($browse, 2);
  }
  // Start the server using 'php -S'.
  if (drush_drupal_major_version() >= 8) {
    $extra = ' "' . __DIR__ . '/d8-rs-router.php"';
  }
  elseif (drush_drupal_major_version() == 7) {
    $extra = ' "' . __DIR__ . '/d7-rs-router.php"';
  }
  else {
    $extra = ' --define auto_prepend_file="' . __DIR__ . '/runserver-prepend.php"';
  }
  $root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  drush_shell_exec_interactive('cd %s && %s -S ' . $addr . ':' . $uri['port']. $extra, $root, drush_get_option('php', 'php'));
}

/**
 * Determine the URI to use for this server.
 */
function runserver_uri($uri) {
  $drush_default = array(
    'host' => '127.0.0.1',
    'port' => '8888',
    'path' => '',
  );
  $user_default = runserver_parse_uri(drush_get_option('default-server', ''));
  $site_default = runserver_parse_uri(drush_get_option('uri', ''));
  $uri = runserver_parse_uri($uri);
  if (is_array($uri)) {
    // Populate defaults.
    $uri = $uri + $user_default + $site_default + $drush_default;
    if (ltrim($uri['path'], '/') == '-') {
      // Allow a path of a single hyphen to clear a default path.
      $uri['path'] = '';
    }
    // Determine and set the new URI.
    $uri['addr'] = $uri['host'];
    if (drush_get_option('dns', FALSE)) {
      if (ip2long($uri['host'])) {
        $uri['host'] = gethostbyaddr($uri['host']);
      }
      else {
        $uri['addr'] = gethostbyname($uri['host']);
      }
    }
  }
  return $uri;
}

/**
 * Parse a URI or partial URI (including just a port, host IP or path).
 *
 * @param string $uri
 *   String that can contain partial URI.
 *
 * @return array
 *   URI array as returned by parse_url.
 */
function runserver_parse_uri($uri) {
  if (empty($uri)) {
    return array();
  }
  if ($uri[0] == ':') {
    // ':port/path' shorthand, insert a placeholder hostname to allow parsing.
    $uri = 'placeholder-hostname' . $uri;
  }
  // FILTER_VALIDATE_IP expects '[' and ']' to be removed from IPv6 addresses.
  // We check for colon from the right, since IPv6 addresses contain colons.
  $to_path = trim(substr($uri, 0, strpos($uri, '/')), '[]');
  $to_port = trim(substr($uri, 0, strrpos($uri, ':')), '[]');
  if (filter_var(trim($uri, '[]'), FILTER_VALIDATE_IP) || filter_var($to_path, FILTER_VALIDATE_IP) || filter_var($to_port, FILTER_VALIDATE_IP)) {
    // 'IP', 'IP/path' or 'IP:port' shorthand, insert a schema to allow parsing.
    $uri = 'http://' . $uri;
  }
  $uri = parse_url($uri);
  if (empty($uri)) {
    return drush_set_error('RUNSERVER_INVALID_ADDRPORT', dt('Invalid argument - should be in the "host:port/path" format, numeric (port only) or non-numeric (path only).'));
  }
  if (count($uri) == 1 && isset($uri['path'])) {
    if (is_numeric($uri['path'])) {
      // Port only shorthand.
      $uri['port'] = $uri['path'];
      unset($uri['path']);
    }
  }
  if (isset($uri['host']) && $uri['host'] == 'placeholder-hostname') {
    unset($uri['host']);
  }
  return $uri;
}
