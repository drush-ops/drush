<?php

// taken from http://api.drupal.org/api/drupal/includes%21bootstrap.inc/constant/DRUPAL_CORE_COMPATIBILITY/7
// not defined! used in commands/make/generate.make.inc, commands/core/drupal/environment.inc
// must come from the project somewhere.
 define('DRUPAL_CORE_COMPATIBILITY', '7.x');

// http://api.drupal.org/api/drupal/includes%21bootstrap.inc/constant/DRUPAL_BOOTSTRAP_CONFIGURATION/7
define('DRUPAL_BOOTSTRAP_CONFIGURATION', 0);
define('DRUPAL_BOOTSTRAP_DATABASE',2); // http://api.drupal.org/api/drupal/includes%21bootstrap.inc/constant/DRUPAL_BOOTSTRAP_DATABASE/7
define('DRUPAL_BOOTSTRAP_FULL',7); // http://api.drupal.org/api/drupal/includes%21bootstrap.inc/constant/DRUPAL_BOOTSTRAP_FULL/7

//was not defined, but we will include Table.php
//taken from http://pear.php.net/package/Console_Table/docs/latest/__filesource/fsource_Console_Table__Console_Table-1.1.4Table.php.html
//define('CONSOLE_TABLE_ALIGN_LEFT', -1); 

require('/usr/share/php/Console/Table.php');
require('/usr/share/php/Console/Color.php');


require('./includes/output.inc');
require('./includes/environment.inc');
require('./includes/command.inc');
require('./includes/drush.inc');
require('./includes/backend.inc');
require('./includes/batch.inc');
require('./includes/context.inc');
require('./includes/sitealias.inc');
require('./includes/exec.inc');
require('./includes/drupal.inc');
require('./includes/output.inc');
require('./includes/cache.inc');
require('./includes/filesystem.inc');
require('./includes/dbtng.inc');
require('./includes/bootstrap.inc');
require('drush.php');
?>