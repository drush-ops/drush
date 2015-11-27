<?php

/**
 * @file
 * pm-info command implementation.
 */

use Drush\Log\LogLevel;

/**
 * Command callback. Show detailed info for one or more extensions.
 */
function drush_pm_info() {
  $result = array();
  $args = pm_parse_arguments(func_get_args());

  $extension_info = drush_get_extensions(FALSE);
  _drush_pm_expand_extensions($args, $extension_info);
  // If no extensions are provided, show all.
  if (count($args) == 0) {
    $args = array_keys($extension_info);
  }

  foreach ($args as $extension) {
    if (isset($extension_info[$extension])) {
      $info = $extension_info[$extension];
    }
    else {
      drush_log(dt('!extension was not found.', array('!extension' => $extension)), LogLevel::WARNING);
      continue;
    }
    if (drush_extension_get_type($info) == 'module') {
      $data = _drush_pm_info_module($info);
    }
    else {
      $data = _drush_pm_info_theme($info);
    }
    $result[$extension] = $data;
  }
  return $result;
}

/**
 * Output format formatter-filter callback.
 *
 * @see drush_parse_command()
 * @see drush_outputformat
 */
function _drush_pm_info_format_table_data($data) {
  $result = array();
  foreach ($data as $extension => $info) {
    foreach($info as $key => $value) {
      if (is_array($value)) {
        if (empty($value)) {
          $value = 'none';
        }
        else {
          $value = implode(', ', $value);
        }
      }
      $result[$extension][$key] = $value;
    }
  }
  return $result;
}

/**
 * Return an array with general info of an extension.
 */
function _drush_pm_info_extension($info) {
  $data['extension'] = drush_extension_get_name($info);
  $data['project'] = isset($info->info['project'])?$info->info['project']:dt('Unknown');
  $data['type'] = drush_extension_get_type($info);
  $data['title'] = $info->info['name'];
  $data['config'] = isset($info->info['configure']) ? $info->info['configure'] : dt('None');
  $data['description'] = $info->info['description'];
  $data['version'] = $info->info['version'];
  $data['date'] = isset($info->info['datestamp']) ? format_date($info->info['datestamp'], 'custom', 'Y-m-d') : NULL;
  $data['package'] = $info->info['package'];
  $data['core'] = $info->info['core'];
  $data['php'] = $info->info['php'];
  $data['status'] = drush_get_extension_status($info);
  $data['path'] = drush_extension_get_path($info);

  return $data;
}

/**
 * Return an array with info of a module.
 */
function _drush_pm_info_module($info) {
  $major_version = drush_drupal_major_version();

  $data = _drush_pm_info_extension($info);
  if ($info->schema_version > 0) {
    $schema_version = $info->schema_version;
  }
  elseif ($info->schema_version == -1) {
    $schema_version = "no schema installed";
  }
  else {
    $schema_version = "module has no schema";
  }
  $data['schema_version'] = $schema_version;
  if ($major_version == 7) {
    $data['files'] = $info->info['files'];
  }
  $data['requires'] = $info->info['dependencies'];

  if ($major_version == 6) {
    $requiredby = $info->info['dependents'];
  }
  else {
    $requiredby = array_keys($info->required_by);
  }
  $data['required_by'] = $requiredby;
  if ($info->status == 1) {
    $role = drush_role_get_class();
    $data['permissions'] = $role->getModulePerms(drush_extension_get_name($info));
  }
  return $data;
}

/**
 * Return an array with info of a theme.
 */
function _drush_pm_info_theme($info) {
  $major_version = drush_drupal_major_version();

  $data = _drush_pm_info_extension($info);

  $data['core'] = $info->info['core'];
  $data['php'] = $info->info['php'];
  $data['engine'] = $info->info['engine'];
  $data['base_theme'] = isset($info->base_themes) ? implode($info->base_themes, ', ') : '';
  $regions = $info->info['regions'];
  $data['regions'] = $regions;
  $features = $info->info['features'];
  $data['features'] = $features;
  if (count($info->info['stylesheets']) > 0) {
    $data['stylesheets'] = '';
    foreach ($info->info['stylesheets'] as $media => $files) {
      $files = array_keys($files);
      $data['media '.$media] = $files;
    }
  }
  if (count($info->info['scripts']) > 0) {
    $scripts = array_keys($info->info['scripts']);
    $data['scripts'] = $scripts;
  }
  return $data;
}
