<?php

namespace Drush\Sql;

class Sql7sqlite extends Sql7 {
  public function command() {
    return 'sqlite3';
  }

  public function creds() {
    // SQLite doesn't do user management, instead relying on the filesystem
    // for that. So the only info we really need is the path to the database
    // file, and not as a "--key=value" parameter.
    return ' '  .  $this->db_spec['database'];
  }
}
