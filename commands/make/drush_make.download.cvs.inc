<?php
// $Id$

class DrushMakeDownload_CVS extends DrushMakeDownload {

  function download() {
    if (isset($this->project->download['module'])) {
      if (drush_get_option('working-copy')) {
        if (isset($_ENV['CVSROOT'])) {
          $this->project->download['root'] = trim($_ENV['CVSROOT'], ':');
        }
        else {
          drush_print(dt('Please set the CVSROOT variable in your shell environment when using the --working-copy option.'));
        }
      }
      // Fallback to anonymous @ cvs.drupal.org
      if (!isset($this->project->download['root'])) {
        $this->project->download['root'] = "pserver:anonymous:anonymous@cvs.drupal.org:/cvs/drupal-contrib";
      }
      $flags = isset($this->project->download['revision']) ? "-r {$this->project->download['revision']}" : '';

      drush_shell_exec("mkdir {$this->path}");
      if (drush_get_option('working-copy')) {
        drush_shell_exec("cvs -d:{$this->project->download['root']} checkout -d {$this->path} {$flags} {$this->project->download['module']}");
        drush_print("{$this->project->name} checkout from {$this->project->download['module']}.");
      }
      else {
        drush_shell_exec("cvs -d:{$this->project->download['root']} export -d {$this->path} {$flags} {$this->project->download['module']}");
        drush_print("{$this->project->name} export from {$this->project->download['module']}.");
      }
      return $this->path;
    }
    return FALSE;
  }
}
