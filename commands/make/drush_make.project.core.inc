<?php
// $Id$

class DrushMakeProject_Core extends DrushMakeProject {
  function __construct(&$project) {
    $project['install_path'] = '';
    parent::__construct($project);
  }
  /**
   * The core project will always be the base of the build, so it needs to move
   * itself into place a little differently.
   */
  function move($project_directory) {
    drush_shell_exec("mv {$project_directory}/* {$this->path}");
    drush_shell_exec("mv {$project_directory}/.* {$this->path}");
    drush_shell_exec("rmdir {$project_directory}");
  }
}
