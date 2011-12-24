<?php

/**
 * Parse Drupal info file format.
 *
 * Copied with modifications from includes/common.inc.
 *
 * @see drupal_parse_info_file
 */
function make_parse_info_file($makefile, $parsed = TRUE) {
  if (!($data = make_get_data($makefile))) {
    return drush_set_error(dt('Invalid or empty make file: %makefile', array('%makefile' => $makefile)));
  }
  if (!($info = _make_parse_info_file($data))) {
    return FALSE;
  }
  if (!empty($info['includes'])) {
    $include_path = dirname($makefile);
    $includes = array();
    if (!empty($info['includes']) && is_array($info['includes'])) {
      foreach ($info['includes'] as $include) {
        if (is_string($include)) {
          if (make_valid_url($include, TRUE) && ($file = make_parse_info_file($include, FALSE))) {
            $includes[] = $file;
          }
          else if (file_exists($include_path .'/'. $include) && ($file = make_parse_info_file($include_path .'/'. $include, FALSE))) {
            $includes[] = $file;
          }
          else if (file_exists($include) && ($file = make_parse_info_file($include, FALSE))) {
            $includes[] = $file;
          }
          else {
            make_error('BUILD_ERROR', dt("Include file missing: %include", array('%include' => $include)));
          }
        }
      }
    }
    $includes[] = $data;
    $data = implode("\n", $includes);
    $info = _make_parse_info_file($data);
  }
  if ($parsed) {
    return $info;
  }
  else {
    return $data;
  }
}

function _make_parse_info_file($data) {
  if (!$data) {
    return FALSE;
  }

  if (preg_match_all('
    @^\s*                           # Start at the beginning of a line, ignoring leading whitespace
    ((?:
      [^=;\[\]]|                    # Key names cannot contain equal signs, semi-colons or square brackets,
      \[[^\[\]]*\]                  # unless they are balanced and not nested
    )+?)
    \s*=\s*                         # Key/value pairs are separated by equal signs (ignoring white-space)
    (?:
      ("(?:[^"]|(?<=\\\\)")*")|     # Double-quoted string, which may contain slash-escaped quotes/slashes
      (\'(?:[^\']|(?<=\\\\)\')*\')| # Single-quoted string, which may contain slash-escaped quotes/slashes
      ([^\r\n]*?)                   # Non-quoted string
    )\s*$                           # Stop at the next end of a line, ignoring trailing whitespace
    @msx', $data, $matches, PREG_SET_ORDER)) {
    $info = array();
    foreach ($matches as $match) {
      // Fetch the key and value string
      $i = 0;
      foreach (array('key', 'value1', 'value2', 'value3') as $var) {
        $$var = isset($match[++$i]) ? $match[$i] : '';
      }
      $value = stripslashes(substr($value1, 1, -1)) . stripslashes(substr($value2, 1, -1)) . $value3;

      // Parse array syntax
      $keys = preg_split('/\]?\[/', rtrim($key, ']'));
      $last = array_pop($keys);
      $parent = &$info;

      // Create nested arrays
      foreach ($keys as $key) {
        if ($key == '') {
          $key = count($parent);
        }
        if (!isset($parent[$key]) || !is_array($parent[$key])) {
          $parent[$key] = array();
        }
        $parent = &$parent[$key];
      }

      // Handle PHP constants
      if (defined($value)) {
        $value = constant($value);
      }

      // Insert actual value
      if ($last == '') {
        $last = count($parent);
      }
      $parent[$last] = $value;
    }
    return $info;
  }
  return FALSE;
}

function make_validate_info_file($info) {
  // Assume no errors to start.
  $errors = FALSE;

  if (empty($info['core'])) {
    make_error('BUILD_ERROR', dt("The 'core' attribute is required"));
    $errors = TRUE;
  }
  // Standardize on core.
  elseif (preg_match('/^(\d+)(\.(x|(\d+)(-[a-z0-9]+)?))?$/', $info['core'], $matches)) {
    // An exact version of core has been specified, so pass that to an
    // internal variable for storage.
    if (isset($matches[4])) {
      $info['core_release'] = $info['core'];
    }
    // Format the core attribute consistently.
    $info['core'] = $matches[1] . '.x';
  }
  else {
    make_error('BUILD_ERROR', dt("The 'core' attribute %core has an incorrect format.", array('%core' => $info['core'])));
    $errors = TRUE;
  }

  if (!isset($info['api'])) {
    $info['api'] = MAKE_API;
    drush_log(dt("You need to specify an API version of two in your makefile:\napi = !api", array("!api" => MAKE_API)), 'warning');
  }
  elseif ($info['api'] != MAKE_API) {
    make_error('BUILD_ERROR', dt("The specified API attribute is incompatible with this version of Drush Make."));
    $errors = TRUE;
  }

  $names = array();

  // Process projects.
  if (isset($info['projects'])) {
    if (!is_array($info['projects'])) {
      make_error('BUILD_ERROR', dt("'projects' attribute must be an array."));
      $errors = TRUE;
    }
    else {
      // Filter out entries that have been forcibly removed via [foo] = FALSE
      $info['projects'] = array_filter($info['projects']);

      foreach ($info['projects'] as $project => $project_data) {
        // Project has an attributes array.
        if (is_string($project) && is_array($project_data)) {
          if (in_array($project, $names)) {
            make_error('BUILD_ERROR', dt("Project %project defined twice (remove the first projects[] = %project).", array('%project' => $project)));
            $errors = TRUE;
          }
          $names[] = $project;
          foreach ($project_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('contrib_destination'))) {
              unset($info['projects'][$project][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the filesystem.
            elseif (in_array($attribute, array('subdir', 'directory_name')) && !make_safe_path($value)) {
              make_error('BUILD_ERROR', dt("Illegal path %path for '%attribute' attribute in project %project.", array('%path' => $value, '%attribute' => $attribute, '%project' => $project)));
              $errors = TRUE;
            }
          }
        }
        // Cover if there is no project info, it's just a project name.
        elseif (is_numeric($project) && is_string($project_data)) {
          if (in_array($project_data, $names)) {
            make_error('BUILD_ERROR', dt("Project %project defined twice (remove the first projects[] = %project).", array('%project' => $project_data)));
            $errors = TRUE;
          }
          $names[] = $project_data;
          unset($info['projects'][$project]);
          $info['projects'][$project_data] = array();
        }
        // Convert shorthand project version style to array format.
        elseif (is_string($project_data)) {
          if (in_array($project, $names)) {
            make_error('BUILD_ERROR', dt("Project %project defined twice (remove the first projects[] = %project).", array('%project' => $project)));
            $errors = TRUE;
          }
          $names[] = $project;
          $info['projects'][$project] = array('version' => $project_data);
        }
        else {
          make_error('BUILD_ERROR', dt('Project %project incorrectly specified.', array('%project' => $project)));
          $errors = TRUE;
        }
      }
    }
  }
  if (isset($info['libraries'])) {
    if (!is_array($info['libraries'])) {
      make_error('BUILD_ERROR', dt("'libraries' attribute must be an array."));
      $errors = TRUE;
    }
    else {
      // Filter out entries that have been forcibly removed via [foo] = FALSE
      $info['libraries'] = array_filter($info['libraries']);

      foreach($info['libraries'] as $library => $library_data) {
        if (is_array($library_data)) {
          foreach($library_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('contrib_destination'))) {
              unset($info['libraries'][$library][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the filesystem.
            elseif (in_array($attribute, array('contrib-destination', 'directory_name')) && !make_safe_path($value)) {
              make_error('BUILD_ERROR', dt("Illegal path %path for '%attribute' attribute in library %library.", array('%path' => $value, '%attribute' => $attribute, '%library' => $library)));
              $errors = TRUE;
            }
          }
        }
      }
    }
  }

  foreach (drush_command_implements('make_validate_info') as $module) {
    $function = $module .'_make_validate_info';
    $return = $function($info);
    if ($return) {
      $info = $return;
    }
    else {
      $errors = TRUE;
    }
  }

  if ($errors) {
    return FALSE;
  }
  return $info;
}

/**
 * Verify the syntax of the given URL.
 *
 * Copied verbatim from includes/common.inc
 *
 * @see valid_url
 */
function make_valid_url($url, $absolute = FALSE) {
  if ($absolute) {
    return (bool)preg_match("
      /^                                                      # Start at the beginning of the text
      (?:ftp|https?):\/\/                                     # Look for ftp, http, or https schemes
      (?:                                                     # Userinfo (optional) which is typically
        (?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*      # a username or a username and password
        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@          # combination
      )?
      (?:
        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
        |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
      )
      (?::[0-9]+)?                                            # Server port number (optional)
      (?:[\/|\?]
        (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
      *)?
    $/xi", $url);
  }
  else {
    return (bool)preg_match("/^(?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})+$/i", $url);
  }
}

/*
 * @todo Merge with drush_tempdir().
 */
function make_tmp($set = TRUE) {
  static $tmp_dir;
  if (!isset($tmp_dir) && $set) {
    $tmp_dir = drush_find_tmp();
    if (strrpos($tmp_dir, '/') == strlen($tmp_dir) - 1) {
      $tmp_dir .= 'make_tmp_' . time() . '_' . uniqid();
    }
    else {
      $tmp_dir .= '/make_tmp_' . time() . '_' . uniqid();
    }
    if (file_exists($tmp_dir)) {
      return make_tmp(TRUE);
    }
    // else create it
    drush_mkdir($tmp_dir);
  }
  return $tmp_dir;
}

function make_clean_tmp() {
  if (!($tmp_dir = make_tmp(FALSE))) {
    return;
  }
  if (!drush_get_option('no-clean', FALSE)) {
    drush_shell_exec('rm -rf %s', $tmp_dir);
  }
  else {
    drush_log(dt('Temporary directory: %dir', array('%dir' => $tmp_dir)), 'ok');
  }
}

function make_prepare_install($build_path) {
  $default = make_tmp() . '/__build__/sites/default';
  drush_shell_exec("cp %s %s", $default . '/default.settings.php', $default . '/settings.php');
  drush_mkdir($default . '/files');
  drush_shell_exec("chmod a+w %s %s", $default . '/settings.php', $default . '/files');
}

function make_md5() {
  if (drush_shell_exec("( find %s -type f -exec cksum {} \; )", make_tmp())) {
    $hashes = array();
    foreach (drush_shell_exec_output() as $line) {
      // Remove the temporary build path which includes a (relatively)
      // unique timestamp.
      $line = str_replace(make_tmp(), '', $line);
      // Trim to sanitize.
      $line = trim($line);
      $hashes[] = $line;
    }
    sort($hashes);
    return md5(implode("\n", $hashes));
  }
}

/*
 * @todo drush_archive_dump() also makes a tar. Consolidate?
 */
function make_tar($build_path) {
  $tmp_path = make_tmp();

  drush_mkdir(dirname($build_path));
  $filename = basename($build_path);
  $dirname = basename($build_path, '.tar.gz');
  // Move the build directory to a more human-friendly name, so that tar will
  // use it instead.
  drush_shell_exec("mv %s %s", $tmp_path . '/__build__', $tmp_path . '/' . $dirname);
  // Only move the tar file to it's final location if it's been built
  // successfully.
  if (drush_shell_exec("tar -C %s -Pczf %s %s", $tmp_path, $tmp_path . '/' . $filename, $dirname)) {
    drush_shell_exec("mv %s %s", $tmp_path . '/' . $filename, $build_path);
  };
  // Move the build directory back to it's original location for consistency.
  drush_shell_exec("mv %s %s", $tmp_path . '/' . $dirname, $tmp_path . '/__build__');
}

/**
 * Logs an error unless the --force-complete command line option is specified.
 */
function make_error($error_code, $message) {
  if (drush_get_option('force-complete')) {
    drush_log("$error_code: $message -- build forced", 'warning');
  }
  else {
    drush_set_error($error_code, $message);
  }
}

/**
 * Checks an attribute's path to ensure it's not maliciously crafted.
 *
 * @param $path
 *   The path to check.
 */
function make_safe_path($path) {
  return !preg_match("+^/|^\.\.|/\.\./+", $path);
}
/**
 * Get data based on the source.
 *
 * This is a helper function to abstract the retrieval of data, so that it can
 * come from files, STDIN, etc.  Currently supports filepath and STDIN.
 *
 * @param $data_source
 *   The path to a file, or '-' for STDIN.
 * @return
 *   The raw data as a string.
 */
function make_get_data($data_source) {
  if ($data_source == '-') {
    // See http://drupal.org/node/499758 before changing this.
    $stdin = fopen('php://stdin', 'r');
    $data = '';
    $has_input = FALSE;

    while ($line = fgets($stdin)) {
      $has_input = TRUE;
      $data .= $line;
    }

    if ($has_input) {
      return $data;
    }
    return FALSE;
  }
  // Local file.
  elseif (!strpos($data_source, '://')) {
    $data = file_get_contents($data_source);
  }
  // Remote file.
  else {
    $file = _make_download_file(array('url' => $data_source));
    $data = file_get_contents($file);
    drush_op('unlink', $file);
  }
  return $data;
}

/**
 * Helper to provide sys_get_temp_dir if on php < 5.2.1
 */
if (!function_exists('sys_get_temp_dir')) {
  // Based on http://www.phpit.net/
  // article/creating-zip-tar-archives-dynamically-php/2/
  function sys_get_temp_dir() {
    // Try to get from environment variable
    if (!empty($_ENV['TMP'])){
      return realpath($_ENV['TMP']);
    }
    else if (!empty($_ENV['TMPDIR'])){
      return realpath($_ENV['TMPDIR']);
    }
    else if (!empty($_ENV['TEMP'])){
      return realpath($_ENV['TEMP']);
    }
    else {
      // Detect by creating a temporary file
      // Try to use system's temporary directory
      // as random name shouldn't exist
      $temp_file = tempnam(md5(uniqid(rand(), TRUE)), '');
      if ($temp_file) {
        $temp_dir = realpath(dirname($temp_file));
        unlink($temp_file);
        return $temp_dir;
      }
      else {
        return FALSE;
      }
    }
  }
}

/**
 * @todo Consolidate with drush_download_file if possible.
 * Drush make parallel to drupal_http_request, but writes responses to a file.
 */
function make_http_request($url, $destination, $headers = array(), $method = 'GET', $data = NULL, $retry = 5) {
  global $db_prefix;

  $result = new stdClass();

  // Parse the URL and make sure we can handle the schema.
  $uri = parse_url($url);

  if ($uri == FALSE) {
    $result->error = 'unable to parse URL';
    $result->code = -1001;
    return $result;
  }

  if (!isset($uri['scheme'])) {
    $result->error = 'missing schema';
    $result->code = -1002;
    return $result;
  }

  switch ($uri['scheme']) {
    case 'http':
    case 'feed':
      $port = isset($uri['port']) ? $uri['port'] : 80;
      $host = $uri['host'] . ($port != 80 ? ':'. $port : '');
      $fp = @fsockopen($uri['host'], $port, $errno, $errstr, 15);
      break;
    case 'https':
      // Note: Only works for PHP 4.3 compiled with OpenSSL.
      $port = isset($uri['port']) ? $uri['port'] : 443;
      $host = $uri['host'] . ($port != 443 ? ':'. $port : '');
      $fp = @fsockopen('ssl://'. $uri['host'], $port, $errno, $errstr, 20);
      break;
    default:
      $result->error = 'invalid schema '. $uri['scheme'];
      $result->code = -1003;
      return $result;
  }

  // Make sure the socket opened properly.
  if (!$fp) {
    // When a network error occurs, we use a negative number so it does not
    // clash with the HTTP status codes.
    $result->code = -$errno;
    $result->error = trim($errstr);

    return $result;
  }

  // Construct the path to act on.
  $path = isset($uri['path']) ? $uri['path'] : '/';
  if (isset($uri['query'])) {
    $path .= '?'. $uri['query'];
  }

  // Create HTTP request.
  $defaults = array(
    // RFC 2616: "non-standard ports MUST, default ports MAY be included".
    // We don't add the port to prevent from breaking rewrite rules checking the
    // host that do not take into account the port number.
    'Host' => "Host: $host",
    'User-Agent' => 'User-Agent: Drupal (+http://drupal.org/)',
  );

  // Only add Content-Length if we actually have any content or if it is a POST
  // or PUT request. Some non-standard servers get confused by Content-Length in
  // at least HEAD/GET requests, and Squid always requires Content-Length in
  // POST/PUT requests.
  $content_length = strlen($data);
  if ($content_length > 0 || $method == 'POST' || $method == 'PUT') {
    $defaults['Content-Length'] = 'Content-Length: '. $content_length;
  }

  // If the server url has a user then attempt to use basic authentication
  if (isset($uri['user'])) {
    $defaults['Authorization'] = 'Authorization: Basic '. base64_encode($uri['user'] . (!empty($uri['pass']) ? ":". $uri['pass'] : ''));
  }

  // If the database prefix is being used by SimpleTest to run the tests in a copied
  // database then set the user-agent header to the database prefix so that any
  // calls to other Drupal pages will run the SimpleTest prefixed database. The
  // user-agent is used to ensure that multiple testing sessions running at the
  // same time won't interfere with each other as they would if the database
  // prefix were stored statically in a file or database variable.
  if (is_string($db_prefix) && preg_match("/^simpletest\d+$/", $db_prefix, $matches)) {
    $defaults['User-Agent'] = 'User-Agent: ' . $matches[0];
  }

  foreach ($headers as $header => $value) {
    $defaults[$header] = $header .': '. $value;
  }

  $request = $method .' '. $path ." HTTP/1.0\r\n";
  $request .= implode("\r\n", $defaults);
  $request .= "\r\n\r\n";
  $request .= $data;

  $result->request = $request;

  fwrite($fp, $request);

  $fp_dest = fopen($destination, 'w');
  $all_headers = FALSE;

  // Fetch response.
  $split = '';
  while (!feof($fp) && $chunk = fread($fp, 1024)) {
    if (strpos($chunk, "\r\n\r\n") !== FALSE && !$all_headers) {
      $all_headers = TRUE;
      list($header, $body) = explode("\r\n\r\n", $chunk, 2);
      $split .= $header;
      fwrite($fp_dest, $body);
    }
    elseif (!$all_headers) {
      $split .= $chunk;
    }
    else {
      fwrite($fp_dest, $chunk);
    }
  }
  fclose($fp_dest);
  fclose($fp);

  // Parse response.
  $split = preg_split("/\r\n|\n|\r/", $split);

  list($protocol, $code, $status_message) = explode(' ', trim(array_shift($split)), 3);
  $result->protocol = $protocol;
  $result->status_message = $status_message;

  $result->headers = array();

  // Parse headers.
  while ($line = trim(array_shift($split))) {
    list($header, $value) = explode(':', $line, 2);
    if (isset($result->headers[$header]) && $header == 'Set-Cookie') {
      // RFC 2109: the Set-Cookie response header comprises the token Set-
      // Cookie:, followed by a comma-separated list of one or more cookies.
      $result->headers[$header] .= ','. trim($value);
    }
    else {
      $result->headers[$header] = trim($value);
    }
  }

  $responses = array(
    100 => 'Continue', 101 => 'Switching Protocols',
    200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content',
    300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect',
    400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Time-out', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Large', 415 => 'Unsupported Media Type', 416 => 'Requested range not satisfiable', 417 => 'Expectation Failed',
    500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Time-out', 505 => 'HTTP Version not supported'
  );
  // RFC 2616 states that all unknown HTTP codes must be treated the same as the
  // base code in their class.
  if (!isset($responses[$code])) {
    $code = floor($code / 100) * 100;
  }

  $result->code = $code;
  return $result;
}
