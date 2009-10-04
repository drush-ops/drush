<?php
// $Id$

class DrushMakeProject_Module extends DrushMakeProject {
  public function __construct(&$project) {
    $project['install_path'] = $project['install_path'] .'/modules';
    parent::__construct($project);
  }
}
