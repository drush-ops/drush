<?php
// $Id$

class DrushMakeDownload {
  var $project;
  var $path;

  function __construct($project) {
    $this->project = $project;
    $this->path = !empty($this->project->directory_name) ? $this->project->directory_name : $this->project->name;
    $this->preDownload();
  }

  function factory($project) {
    $class = 'DrushMakeDownload_' . $project->download['type'];
    if (class_exists($class)) {
      return new $class($project);
    }
    else {
      return FALSE;
    }
  }

  function preDownload() {
    return FALSE;
  }

  function download() {
    return FALSE;
  }
}

class DrushMakeDownload_CVS extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    $command = 'export';
    if (!empty($download['module'])) {
      if (drush_get_option('working-copy')) {
        if (isset($_ENV['CVSROOT'])) {
          $this->project->download['root'] = $download['root'] = trim($_ENV['CVSROOT']);
        }
        else {
          drush_log(dt('Please set the CVSROOT variable in your shell environment when using the --working-copy option.'), 'ok');
        }
      }
      // Fallback to anonymous @ cvs.drupal.org
      if (!isset($download['root'])) {
        $this->project->download['root'] = $download['root'] = ":pserver:anonymous:anonymous@cvs.drupal.org:/cvs/drupal-contrib";
      }
      $flags = isset($download['revision']) ? "-r{$download['revision']}" : '';

      if (drush_get_option('working-copy')) {
        $command = 'checkout';
      }

      // Checkout or export the module. CVS can't use absolute paths for named
      // directories, so change into the tmp directory before checking out. 
      if (drush_make_cd($tmp_path, "cvs -d%s %s -d%s %s %s", $download['root'], $command, $name, $flags, $download['module'])) {
        drush_log(dt('%project @command from %module.', array('%project' => $name, '@command' => $command, '%module' => $download['module'])), 'ok');
        return $name;
      }
    }
    else {
      $download['module'] = dt("unspecified module");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to @command %project from %root %module.', array('%project' => $name, '@command'=> $command, '%root' => $download['root'], '%module' => $download['module'])));
    return FALSE;
  }
}

class DrushMakeDownload_Get extends DrushMakeDownload {
  protected function unpack($filename) {
    if (drush_get_context('DRUSH_SIMULATE')) {
      // Return a fake directory name so our simulation makes more sense.
      return 'simulated_project';
    }

    $extension = array_pop(explode('.', $filename));
    switch ($extension) {
      case 'gz':
      case 'tgz':
        return $this->unpack_gz($filename);
      case 'tar':
        return $this->unpack_tar($filename);
      case 'zip':
        return $this->unpack_zip($filename);
      default:
        drush_make_error('DOWNLOAD_ERROR', dt('Unable to unpack %file', array('%file' => $filename)));
    }
  }

  protected function unpack_tar($filename) {
    // Untar it and then delete the tar file.
    drush_shell_exec('tar -tf %s', $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      list($first_line) = drush_shell_exec_output();
      list($directory) = explode('/', $first_line);
      drush_make_cd($this->project->tmp_path, 'tar -xf %s', $filename);
      drush_op('unlink', $filename);

      return $directory;
    }

    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $filename)));
    return;
  }

  protected function unpack_gz($filename) {
    // Find out where contents will end up. Retrieve last column of output using awk.
    drush_shell_exec("gzip --list %s | awk '{print $4;}'", $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      $info = array_pop($info);
      $matches = array();
      preg_match('/[a-zA-Z0-9.\-_,]*.tar/', $info, $matches);
      if (isset($matches[0])) {
        $file = $this->project->tmp_path . '/' . $matches[0];
        // Unzip it and then delete the tar file.
        drush_shell_exec('gzip -d %s', $filename);
        return $this->unpack_tar($file);
      }
    }
    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $filename)));
    return;
  }

  protected function unpack_zip($filename) {
    // Find out where contents will end up.
    drush_shell_exec("unzip -l %s", $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      foreach ($info as $line) {
        $matches = array();
        preg_match('/^\s+[0-9]+\s+[0-9-]+\s+[0-9:]+\s+(.*)$/', $line, $matches);
        if (isset($matches[1])) {
          $directory = $matches[1];
          break;
        }
      }
      if (isset($directory)) {
        drush_shell_exec("unzip %s -d %s", $filename, $this->project->tmp_path);
        if (file_exists($filename)) {
          drush_shell_exec('rm %s', $filename);
        }
        if (is_dir($this->project->tmp_path . '/__MACOSX')) {
          drush_shell_exec('rm -r %s', $this->project->tmp_path . '/__MACOSX');
        }
        return $directory;
      }
    }
    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $filename)));
    return;
  }

  function download() {
    if ($filename = $this->downloadFile()) {
      return $this->unpack($filename);
    }
    return FALSE;
  }

  protected function downloadFile() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $filename = dt('file');
    if (!empty($this->project->download['url'])) {
      $url = $this->project->download['url'];
      $filename = "$tmp_path/" . basename($url);
      // Download the project -- try wget, fall back to curl.
      if (drush_get_context('DRUSH_SIMULATE') || drush_shell_exec('wget %s -O %s', $url, $filename) || drush_shell_exec('curl -L -o %s %s', $filename, $url)) {
        drush_log(dt('%project downloaded from %url.', array('%project' => $name, '%url' => $url)), 'ok');
        return $filename;
      }
    }
    else {
      $url = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to download %filename from %url.', array('%filename' => $filename, '%url' => $url)));
    return FALSE;
  }
}

class DrushMakeDownload_Post extends DrushMakeDownload_Get {
  protected function downloadFile() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $filename = dt('file');
    if (!empty($this->project->download['url'])) {
      $url = $this->project->download['url'];
      $post_data = $this->project->download['post_data'];
      $file_type = $this->project->download['file_type'];
      $filename = (empty($file_type)) ? "$tmp_path/" . basename($url) : "$tmp_path/" . basename($url) . ".$file_type";
      // Download the project -- try wget, fall back to curl.
      if (drush_get_context('DRUSH_SIMULATE') || drush_shell_exec('wget --post-data=%s %s -O %s', $post_data, $url, $filename) || drush_shell_exec('curl -d %s -o %s %s', $post_data, $filename, $url)) {
        drush_log(dt('%project downloaded from %url.', array('%project' => $name, '%url' => $url)), 'ok');
        return $filename;
      }
    }
    else {
      $url = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to download %filename from %url.', array('%filename' => $filename, '%url' => $url)));
    return FALSE;
  }
}

class DrushMakeDownload_Git extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    if (!empty($download['url'])) {
      // Parse the git repo URL into pieces that we can work with.
      $alt_url = strtr($download['url'], array('git@' => '', 'git://' => ''));
      $alt_url = strpos($alt_url, ':') !== FALSE ? explode(':', $alt_url, 2) : explode('/', $alt_url, 2);
      $alt_url = strpos($download['url'], 'git://') !== FALSE ? "git@{$alt_url[0]}:{$alt_url[1]}" : "git://{$alt_url[0]}/{$alt_url[1]}";

      $this->project->download['branch'] = $download['branch'] = isset($download['branch']) ? $download['branch'] : 'master';
      if (drush_shell_exec("git clone %s %s/%s", $download['url'], $tmp_path, $name)) {
        drush_log(dt('%project cloned from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
      }
      else if (drush_shell_exec("git clone %s %s/%s", $alt_url, $tmp_path, $name)) {
        drush_log(dt("%project url %orig_url failed, using alternative url %alt_url.", array('%project' => $name, '%orig_url' => $download['url'], '%alt_url' => $alt_url)), 'ok');
        drush_log(dt('%project cloned from %url.', array('%project' => $name, '%url' => $alt_url)), 'ok');
      }
      else {
        drush_make_error('DOWNLOAD_ERROR', dt('Unable to clone %project from %url.', array('%project' => $name, '%url' => $download['url'])));
      }
      // Checkout a branch if one was asked for in the make file
      if ($download['branch'] !== 'master') {
        $cwd = getcwd();
        chdir($tmp_path . '/' . $name);
        if (drush_shell_exec("git checkout -b %s origin/%s", $download['branch'], $download['branch'])) {
          drush_log(dt("Checked out branch %branch.", array('%branch' => $download['branch'])), 'ok');
        }
        else {
          drush_make_error('DOWNLOAD_ERROR', dt("Unable to check out branch %branch.", array('%branch' => $download['branch'])));
        }
        chdir($cwd);
      }
      elseif ($download['revision']) {
        $cwd = getcwd();
        chdir($tmp_path . '/' . $name);
        if (drush_shell_exec("git checkout %s", $download['revision'])) {
          drush_log(dt("Checked out revision %revision.", array('%revision' => $download['revision'])), 'ok');
        }
        else {
          drush_make_error('DOWNLOAD_ERROR', dt("Unable to check out revision %revision.", array('%revision' => $download['revision'])));
        }
        chdir($cwd);
      }
      // Remove .git/ directory if working-copy flag was not specified.
      if (!drush_get_option('working-copy') && file_exists($tmp_path . '/' . $name . '/.git')) {
        drush_shell_exec("rm -rf %s/%s/.git", $tmp_path, $name);
      }
      return $name;
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    return FALSE;
  }
}

class DrushMakeDownload_Bzr extends DrushMakeDownload {
  function download() {
    // @TODO implement revisions, tags
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    $command = 'export';
    if (!empty($download['url'])) {
      if (drush_get_option('working-copy')) {
        $command = 'branch';
        if (drush_shell_exec("bzr %s %s %s/%s", $command, $download['url'], $tmp_path, $name)) {
          drush_log(dt('%project branched from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
          return $name;
        }
      }
      // Export has reversed desintation & source syntax
      elseif (drush_shell_exec("bzr %s %s/%s %s", $command, $tmp_path, $name, $download['url'])) {
        drush_log(dt('%project branched from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
        return $name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to branch %project from %url.', array('%project' => $name, '%url' => $download['url'])));
    return FALSE;
  }
}

class DrushMakeDownload_SVN extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    $command = 'export';
    if (!empty($download['url'])) {
      if (drush_get_option('working-copy')) {
        $command = 'checkout';
      }

      $flags = isset($download['revision']) ? "-r{$download['revision']}" : '';

      if (!empty($download['username'])) {
        $result = drush_shell_exec("svn --non-interactive %s %s %s %s/%s --username %s --password %s", $command, $flags, $download['url'], $tmp_path, $name, $download['username'], $download['password']);
      }
      else {
        $result = drush_shell_exec("svn --non-interactive %s %s %s %s/%s", $command, $flags, $download['url'], $tmp_path, $name);
      }
      if ($result) {
        drush_log(dt('%project @command from %url.', array('%project' => $name, '@command' => $command, '%url' => $download['url'])), 'ok');
        return $this->project->name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to @command %project from %url.', array('%project' => $name, '@command' => $command, '%url' => $download['url'])));
    return FALSE;
  }
}

class DrushMakeDownload_Hg extends DrushMakeDownload {
  function download() {
    // @TODO implement revisions, tags
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    if (!empty($download['url'])) {
      if (drush_shell_exec("hg clone %s %s/%s", $download['url'], $tmp_path, $name)) {
        if (!drush_get_option('working-copy')) {
          drush_shell_exec('rm -r %s/%s/.hg', $tmp_path, $name);
        }
        drush_log(dt('%project cloned from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
        return $name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to clone %project from %url.', array('%project' => $name, '%url' => $download['url'])));
    return FALSE;
  }
}

