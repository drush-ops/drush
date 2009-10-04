<?php
// $Id$

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
    drush_shell_exec("gzip --list {$filename} | awk '{print $4;}'");
    $info = drush_shell_exec_output();
    if ($info) {
      $info = array_pop($info);
      $matches = array();
      preg_match('/[a-zA-Z0-9.\-_,]*.tar/', $info, $matches);
      if (isset($matches[0])) {
        $file = $matches[0];

        // Unzip it and then delete the tar file.
        drush_shell_exec('gzip -d ' . $filename);
        drush_shell_exec('tar -tf ' . $file);
        list($directory) = drush_shell_exec_output();
        drush_shell_exec('tar -xf ' . $file);
        drush_op('unlink', $file);

        return $directory;
      }
    }
    drush_set_error(dt('Could not retrieve package information for @filename.', array('@filename' => $filename)));
    return;
  }

  protected function unpack_zip($filename) {
    // Find out where contents will end up. Retrieve last column of output using awk.
    drush_shell_exec("unzip -l {$filename} | awk '{print $4;}'");
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
        drush_shell_exec("unzip {$filename}");
        drush_op('unlink', $filename);
        return $directory;
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
    // Download the project.
    if (!drush_shell_exec("wget " . $this->project->download['url'])) {
      drush_shell_exec("curl -O " . $this->project->download['url']);
    }
    if (file_exists($filename) || drush_get_context('DRUSH_SIMULATE')) {
      drush_print("{$this->project->name} downloaded from {$this->project->download['url']}.");
      return $filename;
    }
    drush_set_error("Unable to download {$filename} from {$this->project->download['url']}.");
    return FALSE;
  }
}
