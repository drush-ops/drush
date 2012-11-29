<?php

/**
 * Command callback. Show detailed info for one or more extensions.
 */
function drush_pm_info() {
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
      drush_log(dt('!extension was not found.', array('!extension' => $extension)), 'warning');
      continue;
    }
    if ($info->type == 'module') {
      $data = _drush_pm_info_module($info);
    }
    else {
      $data = _drush_pm_info_theme($info);
    }
    drush_print_table(drush_key_value_to_array_table($data));
    print "\n";
  }
}

/**
 * Return an array with general info of an extension.
 */
function _drush_pm_info_extension($info) {
  $major_version = drush_drupal_major_version();

  $data['Extension'] = $info->name;
  $data['Project'] = isset($info->info['project'])?$info->info['project']:dt('Unknown');
  $data['Type'] = $info->type;
  $data['Title'] = $info->info['name'];
  $data['Description'] = $info->info['description'];
  $data['Version'] = $info->info['version'];
  $data['Date'] = format_date($info->info['datestamp'], 'custom', 'Y-m-d');
  $data['Package'] = $info->info['package'];
  $data['Core'] = $info->info['core'];
  $data['PHP'] = $info->info['php'];
  $data['Status'] = drush_get_extension_status($info);
  $path = (($info->type == 'module')&&($major_version == 7))?$info->uri:$info->filename;
  $path = substr($path, 0, strrpos($path, '/'));
  $data['Path'] = $path;

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
  $data['Schema version'] = $schema_version;
  if ($major_version == 7) {
    $data['Files'] = implode(', ', $info->info['files']);
  }
  if (count($info->info['dependencies']) > 0) {
    $requires = implode(', ', $info->info['dependencies']);
  }
  else {
    $requires = "none";
  }
  $data['Requires'] = $requires;

  if ($major_version == 6) {
    $requiredby = !empty($info->info['dependents'])?$info->info['dependents']:array("none");
  }
  else {
    $requiredby = !empty($info->required_by)?array_keys($info->required_by):array("none");
  }
  $data['Required by'] = implode(', ', $requiredby);

  return $data;
}

/**
 * Return an array with info of a theme.
 */
function _drush_pm_info_theme($info) {
  $major_version = drush_drupal_major_version();

  $data = _drush_pm_info_extension($info);

  $data['Core'] = $info->info['core'];
  $data['PHP'] = $info->info['php'];
  $data['Engine'] = $info->info['engine'];
  $data['Base theme'] = isset($info->base_themes) ? implode($info->base_themes, ', ') : '';
  $regions = implode(', ', $info->info['regions']);
  $data['Regions'] = $regions;
  $features = implode(', ', $info->info['features']);
  $data['Features'] = $features;
  if (count($info->info['stylesheets']) > 0) {
    $data['Stylesheets'] = '';
    foreach ($info->info['stylesheets'] as $media => $files) {
      $files = implode(', ', array_keys($files));
      $data['Media '.$media] = $files;
    }
  }
  if (count($info->info['scripts']) > 0) {
    $scripts = implode(', ', array_keys($info->info['scripts']));
    $data['Scripts'] = $scripts;
  }
  return $data;
}

