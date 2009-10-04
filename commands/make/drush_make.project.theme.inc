<?php
// $Id$

class DrushMakeProject_Theme extends DrushMakeProject {
  public function __construct(&$project) {
    $project['install_path'] = $project['install_path'] .'/themes';
    parent::__construct($project, $paths);
  }
}
