<?php
// $Id$

class DrushMakeDownload_SVN extends DrushMakeDownload {

  function download() {
    if (isset($this->project->download['url'])) {
      if (drush_get_option('working-copy')) {
        drush_shell_exec("svn --non-interactive checkout {$this->project->download['url']} {$this->path}");
        drush_print("{$this->project->name} checkout from {$this->project->download['url']}.");
      }
      else {
        drush_shell_exec("svn --non-interactive export {$this->project->download['url']} {$this->path}");
        drush_print("{$this->project->name} export from {$this->project->download['url']}.");
      }
      return $this->path;
    }
    return FALSE;
  }
}
