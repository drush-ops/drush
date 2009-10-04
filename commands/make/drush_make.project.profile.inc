<?php
// $Id$

class DrushMakeProject_Profile extends DrushMakeProject {
  public function __construct(&$project) {
    $project['install_path'] = 'profiles';
    parent::__construct($project);
  }
}
