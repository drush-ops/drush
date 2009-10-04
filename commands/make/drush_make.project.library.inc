<?php
// $Id$

class DrushMakeProject_Library extends DrushMakeProject {
  public function __construct(&$project) {
    // Allow libraries to specify where they should live in the build path.
    if (isset($project['destination'])) {
      $project_path = $project['destination'];
    }
    else {
      $project_path = 'libraries';
    }

    $project['install_path'] = $project['install_path'] .'/'. $project_path;
    parent::__construct($project, $paths);
  }
}
