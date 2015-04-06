<?php

namespace Drush\Sql;

class Sqlmongodb extends SqlBase {
  public function command() {
    return 'mongo';
  }

  public function __construct($db_spec = NULL) {
    if (isset($db_spec['host'])) {
      $db_spec['database'] = 'mongodb://' . $db_spec['host'] . '/' . $db_spec['database'];
    }
    parent::__construct($db_spec);
  }

  public function creds($hide_password = TRUE) {
    $parameters = [];
    if ($this->db_spec['username']) {
      $parameters['u'] = $this->db_spec['username'];
    }
    if ($this->db_spec['password']) {
      $parameters['p'] = $this->db_spec['password'];
    }
    $return = $parameters ? $this->params_to_options($parameters) : '';
    // The mongo shell doesn't want the mongodb:// in the beginning.
    return $return . ' ' . substr($this->db_spec['database'], 10);
  }

  public function createdb($quoted = FALSE) {
  }

  public function drop_or_create() {
    $this->query("db.dropDatabase()");
  }
}
