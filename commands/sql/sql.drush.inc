<?php

/**
 * @file
 * Drush sql commands
 */

/**
 * Wrapper for drush_get_class; instantiates an driver-specific instance
 * of SqlBase class.
 *
 * @param array $db_spec
 *   If known, specify a $db_spec that the class can operate with.
 *
 * @throws \Drush\Sql\SqlException
 *
 * @return Drush\Sql\SqlBase
 */
function drush_sql_get_class($db_spec = NULL) {
  $database = drush_get_option('database', 'default');
  $target = drush_get_option('target', 'default');

  // Try a few times to quickly get $db_spec.
  if (!empty($db_spec)) {
    if (!empty($db_spec['driver'])) {
      return drush_get_class(array('Drush\Sql\Sql', 'Drupal\Driver\Database\\' .  $db_spec['driver'] . '\Drush'), array($db_spec), array($db_spec['driver']));
    }
  }
  elseif ($url = drush_get_option('db-url')) {
    $url =  is_array($url) ? $url[$database] : $url;
    $db_spec = drush_convert_db_from_db_url($url);
    $db_spec['db_prefix'] = drush_get_option('db-prefix');
    return drush_sql_get_class($db_spec);
  }
  elseif (($databases = drush_get_option('databases')) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
    $db_spec = $databases[$database][$target];
    return drush_sql_get_class($db_spec);
  }
  else {
    // No parameter or options provided. Determine $db_spec ourselves.
    if ($sqlVersion = drush_sql_get_version()) {
      if ($db_spec = $sqlVersion->get_db_spec()) {
        return drush_sql_get_class($db_spec);
      }
    }
  }

  throw new \Drush\Sql\SqlException('Unable to find a matching SQL Class. Drush cannot find your database connection details.');
}

/**
 * Wrapper for drush_get_class; instantiates a Drupal version-specific instance
 * of SqlVersion class.
 *
 * @return Drush\Sql\SqlVersion
 */
function drush_sql_get_version() {
  return drush_get_class('Drush\Sql\Sql', array(), array(drush_drupal_major_version())) ?: NULL;
}
