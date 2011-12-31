<?php

/**
 * Downloads the given package to the destination directory.
 *
 * @return
 *   The destination path on success, FALSE on failure.
 */
function make_download_factory($name, $download, $download_location) {
  $function = 'make_download_' . $download['type'];
  if (function_exists($function)) {
    return $function($name, $download, $download_location);
  }
  else {
    return FALSE;
  }
}

/**
 * Download project using drush's pm-download command.
 */
function make_download_pm($name, $download, $download_location) {
  $version = $name . '-' . $download['release']['version'];

  $options = array(
    'destination' => dirname($download_location),
    'yes' => TRUE,
    'package-handler' => 'wget',
    'cache' => TRUE,
  );
  if ($name == 'drupal') {
    $options['drupal-project-rename'] = basename($download_location);
  }
  if (drush_get_option('no-cache', FALSE)) {
    unset($options['cache']);
  }

  // Perform actual download with `drush pm-download`.
  drush_invoke_process('@none', 'pm-download', array($version), $options);
}

/**
 * Downloads a file to the specified location.
 *
 * @return
 *   The destination directory on success, FALSE on failure.
 */
function make_download_file($name, $download, $download_location) {
  if ($filename = _make_download_file($download)) {
    if (!drush_get_option('ignore-checksums') && !_make_verify_checksums($download, $filename)) {
      return FALSE;
    }
    drush_log(dt('@project downloaded from @url.', array('@project' => $name, '@url' => $download['url'])), 'ok');
    return make_download_file_unpack($filename, $download_location, (isset($download['filename']) ? $download['filename'] : ''));
  }
  make_error('DOWNLOAD_ERROR', dt('Unable to download @project from @url.', array('@project' => $name, '@url' => $download['url'])));
  return FALSE;
}

function _make_download_file($download) {
  // Static variable to keep track of whether to use curl or wget.
  static $download_mechanism;

  if (!isset($download_mechanism) && drush_get_option('download-mechanism')) {
    $download_mechanism = drush_get_option('download-mechanism');
  }

  $tmp_path = make_tmp();
  $filename = FALSE;
  if (is_string($download)) {
    $download = array('url' => $download);
  }
  if (!isset($download['request_type'])) {
    $download['request_type'] = 'get';
  }
  if (!empty($download['url'])) {
    $url = $download['url'];
    if (drush_get_context('DRUSH_SIMULATE')) {
      $filename = t('file');
    }
    else {
      $success = FALSE;
      $download_path = $tmp_path . '/__download__';
      drush_mkdir($download_path);

      if (!isset($download_mechanism) || $download_mechanism == 'curl') {
        $header_file = $tmp_path . '/__header__';
        touch($header_file);
        drush_shell_exec("ls %s", $download_path);
        $files = drush_shell_exec_output();
        if ($download['request_type'] == 'get' && drush_shell_cd_and_exec($download_path, 'curl -LOD %s %s', $header_file, $url)) {
          $download_mechanism = 'curl';
          $success = TRUE;
        }
        elseif ($download['request_type'] == 'post' && drush_shell_cd_and_exec($download_path, 'curl -d %s -LOD %s %s', $download['data'], $header_file, $url)) {
          $download_mechanism = 'curl';
          $success = TRUE;
        }

        drush_shell_exec("ls %s", $download_path);
        $files_new = drush_shell_exec_output();
        // Can't use list beacuse it's not guarenteed to be at offset 0.
        $diff = array_diff($files_new, $files);
        $filename = $tmp_path . '/__download__/' . array_shift($diff);
        // Determine the proper filename, or at least, the extension.
        $header = explode("\n", trim(str_replace("\r", '', file_get_contents($header_file))));
        $current_header = array();
        $headers = array();
        $first = TRUE;
        foreach ($header as $h) {
          if ($h == '') {
            $headers[] = $current_header;
            $current_header = array();
            $first = TRUE;
          }
          else {
            if (!$first) {
              list($name, $value) = explode(': ', $h, 2);
              $current_header[$name] = $value;
            }
            else {
              $matches = array();
              preg_match('/HTTP\/1\.1 (\d\d\d)/', $h, $matches);
              if (isset($matches[1]) && ($matches[1]{0} == 4 || $matches[1]{0} == 5)) {
                $success = FALSE;
              }
              $first = FALSE;
            }
          }
        }
        if (!empty($current_header)) {
          $headers[] = $current_header;
        }
      }

      if (!isset($download_mechanism) || $download_mechanism == 'make') {
        $retry = 5;
        $url = $download['url'];
        $filename = $download_path . '/__destination__';
        $request_type = strtoupper($download['request_type']);
        $data = (isset($download['data']) ? $download['data'] : '');
        $headers = array();
        while ($retry) {
          $result = make_http_request($url, $filename, array(), $request_type, $data);
          switch ($result->code) {
            case 200: // OK
            case 304: // Not modified
              $retry = FALSE;
              break;
            case 301: // Moved permanently
            case 302: // Moved temporarily
            case 307: // Moved temporarily
              $retry--;

              if ($retry) {
                $url = $result->headers['Location'];
                $request_type = 'GET';
                $data = NULL;
              }
              break;
            default:
              // Oops, error.
              make_error('BUILD_ERROR', $result->error);
              return;
          }
          $download_mechanism = 'make';
          $headers[] = $result->headers;
          $success = TRUE;
        }
      }

      if (!$success) {
        return FALSE;
      }
      // Much more useful in reverse order.
      $headers = array_reverse($headers);
      $content_type = '';
      $file = '';
      foreach ($headers as $key => $header) {
        // Normalize header key casing.
        $headers[$key] = $header = array_change_key_case($header, CASE_LOWER);
        // Check for location header.
        if (isset($header['location'])) {
          $file = basename($header['location']);
        }
      }
      if (isset($headers[0]['content-disposition'])) {
        $parts = explode(';', $headers[0]['content-disposition']);
        foreach ($parts as $part) {
          $inner_parts = explode('=', $part, 2);
          if (trim($inner_parts[0]) == 'filename') {
            $file = basename(trim($inner_parts[1], "\"'"));
            break;
          }
        }
      }
      if (isset($headers[0]['content-type'])) {
        // These still need finalizing.
        switch ($headers[0]['content-type']) {
          case 'application/zip':
            $content_type = 'zip';
            break;
          case 'application/x-gzip':
            $content_type = 'tar.gz';
            break;
          case 'application/x-tar':
            $content_type = 'tar';
            break;
        }
      }
      if (!$file) {
        $file = basename($url);
      }
      if ($content_type) {
        $file .= '.' . $content_type;
      }
      drush_shell_exec('mv %s %s', $filename, $tmp_path . '/' . $file);
      drush_shell_exec('rm -f %s', $tmp_path . '/__header__');
      drush_shell_exec('rm -rf %s', $tmp_path . '/__download__');
      return $tmp_path . '/' . $file;
    }
  }
  return FALSE;
}

/**
 * Unpacks a file to the specified download location.
 *
 * @return
 *   The download location on success, FALSE on failure.
 */
function make_download_file_unpack($filename, $download_location, $name) {
  $extension = array_pop(explode('.', $filename));
  $success = FALSE;
  switch ($extension) {
    case 'gz':
    case 'tgz':
      // I'd like to just use tar -z, but apparently it breaks on windoze. Why do they always have to ruin everything?
      $success = make_download_file_unpack_gzip($filename, $download_location);
      break;
    case 'tar':
      $success = make_download_file_unpack_tar($filename, $download_location);
      break;
    case 'zip':
      $success = make_download_file_unpack_zip($filename, $download_location);
      break;
    default:
      $success = drush_shell_exec('mv %s %s', $filename, $download_location . ($name ? '/' . $name : ''));
  }
  return $success ? $download_location : FALSE;
}

/**
 * Unpacks a tar file to the specified location.
 *
 * @return
 *   TRUE or FALSE depending on whether the operation was successful.
 */
function make_download_file_unpack_tar($filename, $download_location) {
  $tmp_path = make_tmp();

  list($main_directory) = array_reverse(explode('/', $download_location));

  drush_mkdir($tmp_path . '/__unzip__');
  drush_shell_exec('tar -x -C %s -f %s', $tmp_path . '/__unzip__', $filename);

  return _make_download_file_move($tmp_path, $filename, $download_location);
}

/**
 * Unpacks a gzip file to the specified location.
 *
 * @return
 *   TRUE or FALSE depending on whether the operation was successful.
 */
function make_download_file_unpack_gzip($filename, $download_location) {
  // Find out where contents will end up. Retrieve last column of output using awk.
  drush_shell_exec("gzip --list %s", $filename);
  $info = drush_shell_exec_output();
  if ($info) {
    foreach ($info as $line) {
      $matches = array();
      preg_match('/^\s+[0-9]+\s+[0-9-]+\s+[0-9\.%]+\s+(.*)$/', $line, $matches);
      if (isset($matches[1])) {
        $file = $matches[1];
        break;
      }
    }
    if (isset($file)) {
      // Unzip it and then delete the tar file.
      drush_shell_exec('gzip -d %s', $filename);
      return make_download_file_unpack_tar($file, $download_location);
    }
  }
}

function make_download_file_unpack_zip($filename, $download_location) {
  $tmp_path = make_tmp();

  list($main_directory) = array_reverse(explode('/', $download_location));

  drush_mkdir($tmp_path . '/__unzip__');
  drush_shell_exec("unzip %s -d %s", $filename, $tmp_path . '/__unzip__');

  return _make_download_file_move($tmp_path, $filename, $download_location);
}

function _make_download_file_move($tmp_path, $filename, $download_location) {
  drush_shell_exec('ls %s', $tmp_path . '/__unzip__');
  $lines = drush_shell_exec_output();
  $main_directory = basename($download_location);
  if (count($lines) == 1) {
    $directory = array_shift($lines);
    if ($directory != $main_directory) {
      drush_shell_exec('mv %s %s', $tmp_path . '/__unzip__/' . $directory, $tmp_path . '/__unzip__/' . $main_directory);
    }
    drush_shell_exec('cp -Rf %s %s', $tmp_path . '/__unzip__/' . $main_directory, dirname($download_location));
    drush_shell_exec('rm -rf %s', $tmp_path . '/__unzip__');
  }
  elseif (count($lines) > 1) {
    drush_shell_exec('rm -rf %s', $download_location);
    drush_shell_exec('mv %s %s', $tmp_path . '/__unzip__', $download_location);
  }

  // Remove the tarball.
  if (file_exists($filename)) {
    drush_shell_exec('rm %s', $filename);
  }

  if (file_exists($tmp_path . '/__unzip__')) {
    drush_shell_exec('rm -rf %s', $tmp_path . '/__unzip__');
  }
  return TRUE;
}


// Backwards compatibility.
function make_download_get($name, $download, $download_location) {
  return make_download_file($name, $download, $download_location);
}

function make_download_post($name, $download, $download_location) {
  $download['request_type'] = 'post';
  $download['data'] = $download['post_data'];
  return make_download_file($name, $download, $download_location);
}

/**
 * Checks out a git repository to the specified download location.
 *
 * @return
 *   The download location on success, FALSE otherwise.
 */
function make_download_git($name, $download, $download_location) {
  $tmp_path = make_tmp();
  $wc = drush_get_option('working-copy');

  // check if branch option is set in info file, otherwise set default to master branch
  $download['branch'] = isset($download['branch']) ? $download['branch'] : 'master';
  // check if tag option is set in info file, otherwise we set it to false
  $download['tag'] = isset($download['tag']) ? $download['tag'] : FALSE;
  // check if specific revision is set in info file
  $download['revision'] = isset($download['revision']) ? $download['revision'] : FALSE;

  // If no download URL specified, assume anonymous clone from git.drupal.org.
  $download['url'] = isset($download['url']) ? $download['url'] : "git://git.drupal.org/project/$name.git";
  // If no working-copy download URL specified, assume it is the same.
  $download['wc_url'] = isset($download['wc_url']) ? $download['wc_url'] : $download['url'];

  // Use working-copy download URL if --working-copy specified.
  $url = $wc ? $download['wc_url'] : $download['url'];

  $tmp_location = $tmp_path . '/__git__/' . basename($download_location);

  drush_mkdir($tmp_path . '/__git__/');

  // clone the given repository
  if (drush_shell_exec("git clone %s %s", $url, $tmp_location)) {
    drush_log(dt('@project cloned from @url.', array('@project' => $name, '@url' => $url)), 'ok');

    // GIT Checkout only work on a ready cloned repo. So we switch to branch or to tag (only if we have no branch) after cloneing.
    if ($download['branch'] !== 'master' || $download['tag'] || $download['revision'] || !empty($download['submodule'])) {

      // get current directory (for move back later)
      $cwd = getcwd();
      // change path to working copy of cloned repo
      chdir($tmp_location);

      // Progress branch / tag / revision download. Ensure that only one option ist set (branch OR tag OR revision)
      // check if branch a other than master
      if ($download['branch'] !== 'master' && !$download['tag'] && !$download['revision']) {
        if (drush_shell_exec("git checkout %s", $download['branch'])) {
          drush_log(dt("Checked out branch @branch.", array('@branch' => $download['branch'])), 'ok');
        }
        elseif (drush_shell_exec("git checkout -b %s %s", $download['branch'], 'origin/' . $download['branch'])) {
          drush_log(dt("Checked out branch @branch.", array('@branch' => $download['branch'])), 'ok');
        }
        else {
          make_error('DOWNLOAD_ERROR', dt("Unable to check out branch @branch.", array('%branch' => $download['branch'])));
        }
      }
      // progress if: tag is set but not the others
      elseif ($download['branch'] == 'master' && $download['tag'] && !$download['revision']) {
        // @TODO: change checkout to refs path
        if (drush_shell_exec("git checkout %s", 'refs/tags/' . $download['tag'])) {
          drush_log(dt("Checked out tag @tag.", array('@tag' => $download['tag'])), 'ok');
        }
        else {
          make_error('DOWNLOAD_ERROR', dt("Unable to check out tag @tag.", array('@tag' => $download['tag'])));
        }
      }
      // progress if: revision is set but not the others
      elseif ($download['branch'] == 'master' && !$download['tag'] && $download['revision']) {
        if (drush_shell_exec("git checkout %s", $download['revision'])) {
          drush_log(dt("Checked out revision @revision.", array('@revision' => $download['revision'])), 'ok');
        }
        else {
          make_error('DOWNLOAD_ERROR', dt("Unable to checkout revision @revision", array('@revision' => $download['revision'])));
        }
      }
      // more than one option is set so we throw a error message
      elseif ($download['branch'] !== 'master' || $download['tag'] || $download['revision']) {
        make_error('DOWNLOAD_ERROR', dt("You can only specific branch or tag or revision but not combined in make file."));
        return false;
      }
      if (!empty($download['submodule'])) {
        $command = 'git submodule update';
        foreach ($download['submodule'] as $option) {
          $command .= ' --%s';
        }
        if (call_user_func_array('drush_shell_exec', array_merge(array($command), $download['submodule']))) {
          drush_log(dt('Initialized registered submodules.'), 'ok');
        }
        else {
          make_error('DOWNLOAD_ERROR', dt('Unable to initialize submodules.'));
        }
      }
      // move back to last current directory (first line)
      chdir($cwd);
    }

    // Remove .git/ directory if working-copy flag was not specified.
    if (!$wc && file_exists($tmp_location . '/.git')) {
      drush_shell_exec("rm -rf %s", $tmp_location . '/.git');
    }
    drush_shell_exec('cp -Rf %s %s', $tmp_location, dirname($download_location));
    drush_shell_exec("rm -rf %s", dirname($tmp_location));
    return dirname($tmp_location);
  }
  else {
    make_error('DOWNLOAD_ERROR', dt('Unable to clone @project from @url.', array('@project' => $name, '@url' => $url)));
  }

  return FALSE;
}

/**
 * Checks out a Bazaar repository to the specified download location.
 *
 * @return
 *   The download location on success, FALSE otherwise.
 */
function make_download_bzr($name, $download, $download_location) {
  $tmp_path = make_tmp();
  $tmp_location = $tmp_path . '/__bzr__/' . basename($download_location);
  drush_mkdir(dirname($tmp_location));
  if (!empty($download['url'])) {
    $args = array();
    $command = 'bzr';
    if (drush_get_option('working-copy')) {
      $command .= ' branch  --use-existing-dir';
    }
    else {
      $command .= ' export';
    }
    if (isset($download['revision'])) {
      $command .= ' -r %s';
      $args[] = $download['revision'];
    }
    $command .= ' %s %s';
    if (drush_get_option('working-copy')) {
      $args[] = $download['url'];
      $args[] = $tmp_location;
    }
    else {
      $args[] = $tmp_location;
      $args[] = $download['url'];
    }
    array_unshift($args, $command);
    if (call_user_func_array('drush_shell_exec', $args)) {
      drush_log(dt('@project downloaded from @url.', array('@project' => $name, '@url' => $download['url'])), 'ok');
      drush_shell_exec('cp -Rf %s %s', $tmp_location, dirname($download_location));
      drush_shell_exec('rm -rf %s', dirname($tmp_location));
      return dirname($download_location);
    }
  }
  else {
    $download['url'] = dt("unspecified location");
  }
  make_error('DOWNLOAD_ERROR', dt('Unable to download @project from @url.', array('@project' => $name, '@url' => $download['url'])));
  drush_shell_exec('rm -rf %s', dirname($tmp_location));
  return FALSE;
}

/**
 * Checks out an SVN repository to the specified download location.
 *
 * @return
 *   The download location on success, FALSE otherwise.
 */
function make_download_svn($name, $download, $download_location) {
  if (!empty($download['url'])) {
    if (!empty($download['interactive'])) {
      $function = 'drush_shell_exec_interactive';
    }
    else {
      $options = ' --non-interactive';
      $function = 'drush_shell_exec';
    }
    if (!isset($download['force']) || $download['force']) {
      $options = ' --force';
    }
    if (drush_get_option('working-copy')) {
      $command = 'svn' . $options . ' checkout';
    }
    else {
      $command = 'svn' . $options . ' export';
    }

    $args = array();

    if (isset($download['revision'])) {
      $command .= ' -r%s';
      $args[] = $download['revision'];
    }

    $command .= ' %s %s';
    $args[] = $download['url'];
    $args[] = $download_location;

    if (!empty($download['username'])) {
      $command .= ' --username %s';
      $args[] = $download['username'];
      if (!empty($download['password'])) {
        $command .= ' --password %s';
        $args[] = $download['password'];
      }
    }
    array_unshift($args, $command);
    $result = call_user_func_array($function, $args);
    if ($result) {
      drush_log(dt('@project @command from @url.', array('@project' => $name, '@command' => $command, '@url' => $download['url'])), 'ok');
      return $download_location;
    }
    else {
      $download['url'] = dt("unspecified location");
    }
  }
  else {
    make_error('DOWNLOAD_ERROR', dt('Unable to download @project from @url.', array('@project' => $name, '@url' => $download['url'])));
    return FALSE;
  }
}

/**
 * Test that any supplied hash values match the hash of the file content.
 *
 * Unsupported hash algorithms are reported as failure.
 */
function _make_verify_checksums($info, $filename) {
  $hash_algos = array('md5', 'sha1', 'sha256', 'sha512');
  // We only have something to do if a key is an
  // available function.
  if (array_intersect(array_keys($info), $hash_algos)) {
    $content = file_get_contents($filename);
    foreach ($hash_algos as $algo) {
      if (!empty($info[$algo])) {
        $hash = _make_hash($algo, $content);
        if ($hash !== $info[$algo]) {
           make_error('DOWNLOAD_ERROR', dt('Checksum @algo verification failed for @file. Expected @expected, received @hash.', array('@algo' => $algo, '@file' => basename($filename), '@expected' => $info[$algo], '@hash' => $hash)));
          return FALSE;
        }
      }
    }
  }
  return TRUE;
}

/**
 * Calculate the hash of a string for a given algorithm.
 */
function _make_hash($algo, $string) {
  switch ($algo) {
    case 'md5':
      return md5($string);
    case 'sha1':
      return sha1($string);
    default:
      return function_exists('hash') ? hash($algo, $string) : '';
  }
}
