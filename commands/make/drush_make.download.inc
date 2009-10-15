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
    if (isset($this->project->download['module'])) {
      if (drush_get_option('working-copy')) {
        if (isset($_ENV['CVSROOT'])) {
          $this->project->download['root'] = trim($_ENV['CVSROOT'], ':');
        }
        else {
          drush_log(dt('Please set the CVSROOT variable in your shell environment when using the --working-copy option.'), 'ok');
        }
      }
      // Fallback to anonymous @ cvs.drupal.org
      if (!isset($this->project->download['root'])) {
        $this->project->download['root'] = "pserver:anonymous:anonymous@cvs.drupal.org:/cvs/drupal-contrib";
      }
      $flags = isset($this->project->download['revision']) ? "-r {$this->project->download['revision']}" : '';

      drush_op('mkdir', $this->path);
      if (drush_get_option('working-copy')) {
        drush_make_shell_exec("cvs -d:%s checkout -d %s/%s %s %s", $this->project->download['root'], $this->project->tmp_path, $this->project->name, $flags, $this->project->download['module']);
        drush_log($this->project->name . ' checkout from ' . $this->project->download['module'] . '.', 'ok');
      }
      else {
        drush_make_shell_exec("cvs -d:%s export -d %s/%s %s %s", $this->project->download['root'], $this->project->tmp_path, $this->project->name, $flags, $this->project->download['module']);
        drush_log("{$this->project->name} export from {$this->project->download['module']}.", 'ok');
      }
      return $this->project->tmp_path . '/' . $this->project->name;
    }
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
      case 'zip':
        return $this->unpack_zip($filename);
    }
  }

  protected function unpack_gz($filename) {
    // Find out where contents will end up. Retrieve last column of output using awk.
    drush_make_shell_exec("gzip --list %s | awk '{print $4;}'", $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      $info = array_pop($info);
      $matches = array();
      preg_match('/[a-zA-Z0-9.\-_,]*.tar/', $info, $matches);
      if (isset($matches[0])) {
        $file = $this->project->tmp_path . '/' . $matches[0];

        // Unzip it and then delete the tar file.
        drush_make_shell_exec('gzip -d %s', $filename);
        drush_make_shell_exec('tar -tf %s', $file);
        list($directory) = drush_shell_exec_output();
        drush_make_shell_exec('tar -xf %s', $file);
        drush_op('unlink', $file);

        return $directory;
      }
    }
    drush_set_error(dt('Could not retrieve package information for @filename.', array('@filename' => $filename)));
    return;
  }

  protected function unpack_zip($filename) {
    // Find out where contents will end up. Retrieve last column of output using awk.
    drush_make_shell_exec("unzip -l %s | awk '{print $4;}'", $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      foreach ($info as $line) {
        $matches = array();
        preg_match('/[a-zA-Z0-9.\-_,]*\//', $line, $matches);
        if (isset($matches[0])) {
          $directory = $matches[0];
          break;
        }
      }
      if (isset($directory)) {
        drush_make_shell_exec("unzip %s -d %s", $filename, $this->project->tmp_path);
        if (file_exists($filename)) {
          drush_make_shell_exec('rm %s', $filename);
        }
        if (is_dir($this->project->tmp_path . '/__MACOSX')) {
          drush_make_shell_exec('rm -r %s', $this->project->tmp_path . '/__MACOSX');
        }
        return $this->project->tmp_path . '/' . $directory;
      }
    }
    drush_set_error(dt('Could not retrieve package information for @filename.', array('@filename' => $filename)));
    return;
  }

  function download() {
    if ($filename = $this->downloadFile()) {
      return $this->unpack($filename);
    }
    return FALSE;
  }

  protected function downloadFile() {
    list($filename) = array_reverse(explode('/', $this->project->download['url']));
    $filename = $this->project->tmp_path . '/' . $filename;
    // Download the project.
    if (!drush_make_shell_exec('wget %s -O %s', $this->project->download['url'], $filename)) {
      drush_make_shell_exec('curl -O %s', $this->project->download['url'], $filename);
    }
    if (file_exists($filename) || drush_get_context('DRUSH_SIMULATE')) {
      drush_log($this->project->name . ' downloaded from ' . $this->project->download['url'] . '.', 'ok');
      return $filename;
    }
    drush_set_error('Unable to download ' . $filename . ' from ' . $this->project->download['url'] . '.');
    return FALSE;
  }
}

class DrushMakeDownload_Git extends DrushMakeDownload {
  function download() {
    // @TODO implement revisions, tags
    if (isset($this->project->download['url'])) {
      $this->project->download['branch'] = isset($this->project->download['branch']) ? $this->project->download['branch'] : 'master';
      drush_make_shell_exec("git clone %s %s/%s", $this->project->download['url'], $this->project->tmp_path, $this->project->name);
      drush_log("{$this->project->name} cloned from {$this->project->download['url']}.", 'ok');
      // Checkout a branch if one was asked for in the make file
      if ($this->project->download['branch'] !== 'master') {
        drush_shell_exec("cd %s/%s && git checkout -b %s origin/%s", $this->project->tmp_path, $this->project->name, $this->project->download['branch'], $this->project->download['branch']);
        drush_log("Checked out branch " . $this->project->download['branch'] . '.', 'ok');
      }
      return $this->project->tmp_path . '/' . $this->project->name;
    }
    return FALSE;
  }
}

class DrushMakeDownload_SVN extends DrushMakeDownload {
  function download() {
    if (isset($this->project->download['url'])) {
      if (drush_get_option('working-copy')) {
        drush_make_shell_exec("svn --non-interactive checkout %s %s/%s", $this->project->download['url'], $this->project->tmp_path, $this->project->name);
        drush_log($this->project->name . ' downloaded from ' . $this->project->download['url'] . '.', 'ok');
      }
      else {
        drush_make_shell_exec("svn --non-interactive export %s %s/%s", $this->project->download['url'], $this->project->tmp_path, $this->project->name);
        drush_log($this->project->name . ' downloaded from ' . $this->project->download['url'] . '.', 'ok');
      }
      return $this->project->tmp_path . '/' . $this->project->name;
    }
    return FALSE;
  }
}
