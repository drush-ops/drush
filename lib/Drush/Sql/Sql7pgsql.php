<?php

namespace Drush\Sql;

class Sql7pgsql extends Sql7 {

  public function command() {
    return 'psql';
  }

  public function creds() {
    // Some drush commands (e.g. site-install) want to connect to the
    // server, but not the database.  Connect to the built-in database.
    $parameters['dbname'] = empty($this->db_spec['database']) ? 'template1' : $this->db_spec['database'];

    // Host and port are optional but have defaults.
    $parameters['host'] = empty($this->db_spec['host']) ? 'localhost' : $this->db_spec['host'];
    $parameters['port'] = empty($this->db_spec['port']) ? '5432' : $this->db_spec['port'];

    // Username is required.
    $parameters['username'] = $this->db_spec['username'];

    // Don't set the password.
    // @see http://drupal.org/node/438828

    return $this->params_to_options($parameters);
  }
}
