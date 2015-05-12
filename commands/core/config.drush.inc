<?php

/**
 * @file
 *   Provides Configuration Management commands.
 */

use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\FileStorage;
use Symfony\Component\Yaml\Parser;

/**
 * Implementation of hook_drush_help().
 */
function config_drush_help($section) {
  switch ($section) {
    case 'meta:config:title':
      return dt('Config commands');
    case 'meta:config:summary':
      return dt('Interact with the configuration system.');
    case 'drush:config-merge':
      return dt('Combine configuration data from this site with the configuration data of another site; if there are conflicts, open an interactive three-way merge tool comparing the changes with the base revision');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function config_drush_command() {
  $items['config-get'] = array(
    'description' => 'Display a config value, or a whole configuration object.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'The config key, for example "page.front". Optional.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'source' => array(
        'description' => 'The config storage source to read. recognized values are \'active \'and \'staging\'.',
        'example-value' => 'active',
        'value' => 'required',
      ),
      'include-overridden' => array(
        'description' => 'Include overridden values.',
      )
    ),
    'examples' => array(
      'drush config-get system.site' => 'Displays the system.site config.',
      'drush config-get system.site page.front' => 'gets system.site:page.front value.',
    ),
    'outputformat' => array(
      'default' => 'yaml',
      'pipe-format' => 'var_export',
    ),
    'aliases' => array('cget'),
    'core' => array('8+'),
  );

  $items['config-set'] = array(
    'description' => 'Set config value directly in active configuration.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'The config key, for example "page.front".',
      'value' => 'The value to assign to the config key. Use \'-\' to read from STDIN.',
    ),
    'options' => array(
      'format' => array(
        'description' => 'Format to parse the object. Use "string" for string (default), and "yaml" for YAML.',
        'example-value' => 'yaml',
        'value' => 'required',
      ),
      // A convenient way to pass a multiline value within a backend request.
      'value' => array(
        'description' => 'The value to assign to the config key (if any).',
        'hidden' => TRUE,
      ),
    ),
    'examples' => array(
      'drush config-set system.site page.front node' => 'Sets system.site:page.front to "node".',
    ),
    'aliases' => array('cset'),
    'core' => array('8+'),
  );

  $items['config-export'] = array(
    'description' => 'Export config from the active directory.',
    'core' => array('8+'),
    'aliases' => array('cex'),
    'arguments' => array(
      'label' => "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'staging'",
    ),
    'options' => array(
      'add' => 'Run `git add -p` after exporting. This lets you choose which config changes to stage for commit.',
      'commit' => 'Run `git add -A` and `git commit` after exporting.  This commits everything that was exported without prompting.',
      'message' => 'Commit comment for the exported configuration.  Optional; may only be used with --commit or --push.',
      'push' => 'Run `git push` after committing.  Implies --commit.',
      'remote' => array(
        'description' => 'The remote git branch to use to push changes.  Defaults to "origin".',
        'example-value' => 'origin',
      ),
      'branch' => array(
        'description' => 'Make commit on provided working branch. Ignored if used without --commit or --push.',
        'example-value' => 'branchname',
      ),
      'destination' => 'An arbitrary directory that should receive the exported files. An alternative to label argument',
    ),
  );

  $items['config-import'] = array(
    'description' => 'Import config from a config directory.',
    'arguments' => array(
      'label' => "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'staging'",
    ),
    'options' => array(
      'preview' => array(
        'description' => 'Format for displaying proposed changes. Recognized values: list, diff. Defaults to list',
        'example-value' => 'list',
      ),
      'source' => 'An arbitrary directory that holds the configuration files. An alternative to label argument',
      'partial' => 'Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted).',
    ),
    'core' => array('8+'),
    'aliases' => array('cim'),
  );

  $items['config-list'] = array(
    'description' => 'List config names by prefix.',
    'core' => array('8+'),
    'aliases' => array('cli'),
    'arguments' => array(
      'prefix' => 'The config prefix. For example, "system". No prefix will return all names in the system.',
    ),
    'examples' => array(
      'drush config-list system' => 'Return a list of all system config names.',
      'drush config-list "image.style"' => 'Return a list of all image styles.',
      'drush config-list --format="json"' => 'Return all config names as json.',
    ),
    'outputformat' => array(
      'default' => 'list',
      'pipe-format' => 'var_export',
      'output-data-type' => 'format-list',
    ),
  );

  $items['config-edit'] = array(
    'description' => 'Open a config file in a text editor. Edits are imported into active configration after closing editor.',
    'core' => array('8+'),
    'aliases' => array('cedit'),
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
    ),
    'options' => array(
      'bg' => 'Run editor in the background. Does not work with editors such as `vi` that run in the terminal. Supresses config-import at the end.',
      'file' => 'Import from a file instead of interactively editing a given config.',
    ),
    'examples' => array(
      'drush config-edit image.style.large' => 'Edit the image style configurations.',
      'drush config-edit' => 'Choose a config file to edit.',
      'drush config-edit --choice=2' => 'Edit the second file in the choice list.',
      'drush --bg config-edit image.style.large' => 'Return to shell prompt as soon as the editor window opens.',
    ),
  );
  $items['config-merge'] = array(
    'description' => 'Merge configuration data from two sites.',
    'aliases' => array('cm'),
    'required-arguments' => 1,
    'arguments' => array(
      'site' => 'Alias for the site containing the other configuration data to merge.',
      'label' => "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'staging'",
    ),
    'options' => array(
      'base' => 'The commit hash or tag for the base of the three-way merge operation.  This should be the most recent commit that was deployed to the site specified in the first argument.',
      'branch' => array(
        'description' => 'A branch to use when doing the configuration merge. Optional. Default is to use a temporary branch.',
        'example-value' => 'branch-name',
      ),
      'message' => 'Commit comment for the merged configuration.',
      'no-commit' => 'Do not commit the fetched configuration; leave the modified files unstaged.',
      'tool' => array(
        'description' => 'Specific tool to use with `git mergetool`.  Use --tool=0 to prevent use of mergetool.  Optional.  Defaults to whatever tool is configured in git.',
        'example-value' => 'kdiff3',
      ),
      'fetch-only' => "Don't run `git mergetool`; fetch all configuration changes from both sites, and merge them onto the working branch.  May result in unresolved merge conflicts.",
      'git' => "Fetch changes from the other site using git instead of rsync.",
      'remote' => array(
        'description' => 'The remote git branch to use to fetch changes.  Defaults to "origin".',
        'example-value' => 'origin',
      ),
      'temp' => array(
        'description' => "Export destination site's configuration to a temporary directory.",
        'example-value' => 'path',
      ),
    ),
    'examples' => array(
      'drush @dev config-merge @production' => 'Merge configuration changes from the production site with the configuration changes made on the development site.',
      'drush @dev config-merge /path/to/drupal#sitefolder' => 'Merge configuration changes from the site indicated by the provided site specification.',
    ),
    'topics' => array('docs-cm'),
  );

  return $items;
}

/**
 * Config list command callback
 *
 * @param string $prefix
 *   The config prefix to retrieve, or empty to return all.
 */
function drush_config_list($prefix = '') {
  $names = \Drupal::configFactory()->listAll($prefix);

  if (empty($names)) {
    // Just in case there is no config.
    if (!$prefix) {
      return drush_set_error(dt('No config storage names found.'));
    }
    else {
      return drush_set_error(dt('No config storage names found matching @prefix', array('@prefix' => $prefix)));
    }
  }

  return $names;
}

/**
 * Config get command callback.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 */
function drush_config_get($config_name, $key = NULL) {
  if (!isset($key)) {
    return drush_config_get_object($config_name);
  }
  else {
    return drush_config_get_value($config_name, $key);
  }
}

/**
 * Config set command callback.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 * @param $data
 *    The data to save to config.
 */
function drush_config_set($config_name, $key = NULL, $data = NULL) {
  // This hidden option is a convenient way to pass a value without passing a key.
  $data = drush_get_option('value', $data);

  if (!isset($data)) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No config value specified.'));
  }

  $config = Drupal::configFactory()->getEditable($config_name);
  // Check to see if config key already exists.
  if ($config->get($key) === NULL) {
    $new_key = TRUE;
  }
  else {
    $new_key = FALSE;
  }

  // Special flag indicating that the value has been passed via STDIN.
  if ($data === '-') {
    $data = stream_get_contents(STDIN);
  }

  // Now, we parse the value.
  switch (drush_get_option('format', 'string')) {
    case 'yaml':
      $parser = new Parser();
      $data = $parser->parse($data, TRUE);
  }

  if (is_array($data) && drush_confirm(dt('Do you want to update or set multiple keys on !name config.', array('!name' => $config_name)))) {
    foreach ($data as $key => $value) {
      $config->set($key, $value);
    }
    return $config->save();
  }
  else {
    $confirmed = FALSE;
    if ($config->isNew() && drush_confirm(dt('!name config does not exist. Do you want to create a new config object?', array('!name' => $config_name)))) {
      $confirmed = TRUE;
    }
    elseif ($new_key && drush_confirm(dt('!key key does not exist in !name config. Do you want to create a new config key?', array('!key' => $key, '!name' => $config_name)))) {
      $confirmed = TRUE;
    }
    elseif (drush_confirm(dt('Do you want to update !key key in !name config?', array('!key' => $key, '!name' => $config_name)))) {
      $confirmed = TRUE;
    }
    if ($confirmed && !drush_get_context('DRUSH_SIMULATE')) {
      return $config->set($key, $data)->save();
    }
  }
}

function drush_config_export_validate() {
  if ($destination = drush_get_option('destination')) {
    $additional = array();
    $values = drush_sitealias_evaluate_path($destination, $additional, TRUE);
    if (!isset($values['path'])) {
      return drush_set_error('config_export_target', 'The destination directory could not be evaluated.');
    }
    $destination = $values['path'];
    drush_set_option('destination', $destination);
    if (!file_exists($destination)) {
      $parent = dirname($destination);
      if (!is_dir($parent)) {
        return drush_set_error('config_export_target', 'The destination parent directory does not exist.');
      }
      if (!is_writable($parent)) {
        return drush_set_error('config_export_target', 'The destination parent directory is not writable.');
      }
    }
    else {
      if (!is_dir($destination)) {
        return drush_set_error('config_export_target', 'The destination is not a directory.');
      }
      if (!is_writable($destination)) {
        return drush_set_error('config_export_target', 'The destination directory is not writable.');
      }
    }
  }
}

/**
 * Command callback: Export config to specified directory (usually staging).
 */
function drush_config_export($destination = NULL) {
  global $config_directories;

  // Determine which target directory to use.
  if ($target = drush_get_option('destination')) {
    $destination_dir = $target;
    // It is important to be able to specify a destination directory that
    // does not exist yet, for exporting on remote systems
    drush_mkdir($destination_dir);
  }
  else {
    $choices = drush_map_assoc(array_keys($config_directories));
    unset($choices[CONFIG_ACTIVE_DIRECTORY]);
    if (!isset($destination) && count($choices) >= 2) {
      $destination = drush_choice($choices, 'Choose a destination.');
      if (empty($destination)) {
        return drush_user_abort();
      }
    }
    elseif (!isset($destination)) {
      $destination = CONFIG_STAGING_DIRECTORY;
    }
    $destination_dir = config_get_config_directory($destination);
  }

  // Prepare a new branch, if applicable
  $remote = drush_get_option('push', FALSE);
  $original_branch = FALSE;
  $branch = FALSE;
  if ($remote) {
    // Get the branch that we're on at the moment
    $result = drush_shell_cd_and_exec($destination_dir, 'git rev-parse --abbrev-ref HEAD');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_EXPORT_NO_GIT', dt("The drush config-export command requires that the selected configuration directory !dir be under git revision control when using --commit or --push options.", array('!dir' => $destination_dir)));
    }
    $output = drush_shell_exec_output();
    $original_branch = $output[0];
    $branch = drush_get_option('branch', FALSE);
    if (!$branch) {
      $branch = $original_branch;
    }
    if ($branch != $original_branch) {
      // Switch to the working branch; create it if it does not exist.
      // We do NOT want to use -B here, as we do NOT want to reset the
      // branch if it already exists.
      $result = drush_shell_cd_and_exec($destination_dir, 'git checkout %s', $branch);
      if (!$result) {
        $result = drush_shell_cd_and_exec($destination_dir, 'git checkout -b %s', $branch);
      }
    }
  }

  // Do the actual config export operation
  $result = _drush_config_export($destination, $destination_dir, $branch);

  // Regardless of the result of the export, reset to our original branch.
  if ($branch != $original_branch) {
    drush_shell_cd_and_exec($destination_dir, 'git checkout %s', $original_branch);
  }

  return $result;
}

function _drush_config_export($destination, $destination_dir, $branch) {
  $commit = drush_get_option('commit');
  $comment = drush_get_option('message', FALSE);
  if (!$comment) {
    $comment = "Exported configuration.";
  }
  if (count(glob($destination_dir . '/*')) > 0) {
    // Retrieve a list of differences between the active and target configuration (if any).
    $target_storage = new FileStorage($destination_dir);
    /** @var \Drupal\Core\Config\StorageInterface $active_storage */
    $active_storage = Drupal::service('config.storage');
    $config_comparer = new StorageComparer($active_storage, $target_storage, Drupal::service('config.manager'));
    if (!$config_comparer->createChangelist()->hasChanges()) {
      return drush_log(dt('There are no changes to export.'), 'ok');
    }

    drush_print("The following configuration changes have been made since the last export:\n");
    $change_list = array();
    foreach ($config_comparer->getAllCollectionNames() as $collection) {
      $change_list[$collection] = $config_comparer->getChangelist(NULL, $collection);
    }
    // Print a table with changes in color, then re-generate again without
    // color to place in the commit comment.
    _drush_print_config_changes_table($change_list);
    $tbl = _drush_format_config_changes_table($change_list);
    $output = $tbl->getTable();
    if (!stristr(PHP_OS, 'WIN')) {
      $output = str_replace("\r\n", PHP_EOL, $output);
    }
    $comment .= "\n\n$output";

    if (!$commit && !drush_confirm(dt('The current contents of your export directory (!target) will be deleted.', array('!target' => $destination_dir)))) {
      return drush_user_abort();
    }
    // Only delete .yml files, and not .htaccess or .git.
    drush_scan_directory($destination_dir, '/\.yml$/', array('.', '..'), 'unlink');
  }

  // Write all .yml files.
  $source_storage = Drupal::service('config.storage');
  $destination_storage = new FileStorage($destination_dir);
  foreach ($source_storage->listAll() as $name) {
    $destination_storage->write($name, $source_storage->read($name));
  }

  // Export configuration collections.
  foreach (\Drupal::service('config.storage')->getAllCollectionNames() as $collection) {
    $source_storage = $source_storage->createCollection($collection);
    $destination_storage = $destination_storage->createCollection($collection);
    foreach ($source_storage->listAll() as $name) {
      $destination_storage->write($name, $source_storage->read($name));
    }
  }

  drush_log(dt('Configuration successfully exported to !target.', array('!target' => $destination_dir)), 'success');
  drush_backend_set_result($destination_dir);

  // Commit and push, or add exported configuration if requested.
  $remote = drush_get_option('push', FALSE);
  if ($commit || $remote) {
    // There must be changed files at the destination dir; if there are not, then
    // we will skip the commit-and-push step
    $result = drush_shell_cd_and_exec($destination_dir, 'git status --porcelain .');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git status` failed."));
    }
    $uncommitted_changes = drush_shell_exec_output();
    if (!empty($uncommitted_changes)) {
      $result = drush_shell_cd_and_exec($destination_dir, 'git add -A .');
      if (!$result) {
        return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git add -A` failed."));
      }
      $comment_file = drush_save_data_to_temp_file($comment);
      $result = drush_shell_cd_and_exec($destination_dir, 'git commit --file=%s', $comment_file);
      if (!$result) {
        return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git commit` failed.  Output:\n\n!output", array('!output' => implode("\n", drush_shell_exec_output()))));
      }
      if ($remote) {
        // Remote might be FALSE, if --push was not specified, or
        // it might be TRUE if --push was not given a value.
        if (!is_string($remote)) {
          $remote = 'origin';
        }
        $result = drush_shell_cd_and_exec($destination_dir, 'git push --set-upstream %s %s', $remote, $branch);
        if (!$result) {
          return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git push` failed."));
        }
      }
    }
  }
  elseif (drush_get_option('add')) {
    drush_shell_exec_interactive('git add -p %s', $destination_dir);
  }

  $values = array(
    'destination' => $destination_dir,
  );
  return $values;
}

function drush_config_import_validate() {
  if ($source = drush_get_option('source')) {
    if (!file_exists($source)) {
      return drush_set_error('config_import_target', 'The source directory does not exist.');
    }
    if (!is_dir($source)) {
      return drush_set_error('config_import_target', 'The source is not a directory.');
    }
  }
}

/**
 * Command callback. Import from specified config directory (defaults to staging).
 */
function drush_config_import($source = NULL) {
  global $config_directories;

  if ($target = drush_get_option('source')) {
    $source_dir = $target;
  }
  else {
    $choices = drush_map_assoc(array_keys($config_directories));
    unset($choices[CONFIG_ACTIVE_DIRECTORY]);
    if (!isset($source) && count($choices) >= 2) {
      $source= drush_choice($choices, 'Choose a source.');
      if (empty($source)) {
        return drush_user_abort();
      }
    }
    elseif (!isset($source)) {
      $source = CONFIG_STAGING_DIRECTORY;
    }
    $source_dir = config_get_config_directory($source);
  }

  // Retrieve a list of differences between the active and source configuration (if any).
  $source_storage = new FileStorage($source_dir);
  /** @var \Drupal\Core\Config\StorageInterface $active_storage */
  $active_storage = Drupal::service('config.storage');
  if (drush_get_option('partial', FALSE)) {
    // With partial imports, the comparison must only be made against configs
    // that exist in the source directory.
    $temp_active_storage = new FileStorage(drush_tempdir());
    foreach ($source_storage->listAll() as $name) {
      // Copy active storage to our temporary active store.
      if ($existing = $active_storage->read($name)) {
        $temp_active_storage->write($name, $existing);
      }
    }
    $active_storage = $temp_active_storage;
  }
  $config_comparer = new StorageComparer($source_storage, $active_storage, Drupal::service('config.manager'));
  if (!$config_comparer->createChangelist()->hasChanges()) {
    return drush_log(dt('There are no changes to import.'), 'ok');
  }

  if (drush_get_option('preview', 'list') == 'list') {
    $change_list = array();
    foreach ($config_comparer->getAllCollectionNames() as $collection) {
      $change_list[$collection] = $config_comparer->getChangelist(NULL, $collection);
    }
    _drush_print_config_changes_table($change_list);
  }
  else {
    $destination_dir = drush_tempdir();
    drush_invoke_process('@self', 'config-export', array(), array('destination' => $destination_dir));
    // @todo Can DiffFormatter produce a CLI pretty diff?
    drush_shell_exec('diff -x %s -u %s %s', '*.git', $destination_dir, $source_dir);
    $output = drush_shell_exec_output();
    drush_print(implode("\n", $output));
  }

  if (drush_confirm(dt('Import the listed configuration changes?'))) {
    if (drush_get_option('partial')) {
      // Partial imports require different processing.
      return drush_op('_drush_config_import_partial', $source_storage);
    }
    return drush_op('_drush_config_import', $config_comparer);
  }
}

// Copied from submitForm() at /core/modules/config/src/Form/ConfigSync.php
function _drush_config_import(StorageComparer $storage_comparer) {
  $config_importer = new ConfigImporter(
    $storage_comparer,
    Drupal::service('event_dispatcher'),
    Drupal::service('config.manager'),
    Drupal::lock(),
    Drupal::service('config.typed'),
    Drupal::moduleHandler(),
    Drupal::service('module_installer'),
    Drupal::service('theme_handler'),
    Drupal::service('string_translation')
  );
  if ($config_importer->alreadyImporting()) {
    drush_log('Another request may be synchronizing configuration already.', 'warning');
  }
  else{
    try {
      $config_importer->import();
      drupal_flush_all_caches();
      drush_log('The configuration was imported successfully.', 'success');
    }
    catch (ConfigException $e) {
      // Return a negative result for UI purposes. We do not differentiate
      // between an actual synchronization error and a failed lock, because
      // concurrent synchronizations are an edge-case happening only when
      // multiple developers or site builders attempt to do it without
      // coordinating.
      watchdog_exception('config_import', $e);
      return drush_set_error('config_import_fail', 'The import failed due to an error. Any errors have been logged.');
    }
  }
}

/**
 * Imports a partial set of configurations.
 */
function _drush_config_import_partial(FileStorage $source) {
  /** @var \Drupal\Core\Config\StorageInterface $active_storage */
  $active_storage = Drupal::service('config.storage');
  foreach ($source->listAll() as $name) {
    $active_storage->write($name, $source->read($name));
  }
}

/**
 * Edit command callback.
 */
function drush_config_edit($config_name = '') {
  if (empty($config_name) && $file = drush_get_option('file', FALSE)) {
    // If not provided, assume config name from the given file.
    $config_name = basename($file, '.yml');
  }

  // Identify and validate input.
  if ($config_name) {
    $config = Drupal::configFactory()->getEditable($config_name);
    if ($config->isNew()) {
      return drush_set_error(dt('Config !name does not exist', array('!name' => $config_name)));
    }
  }
  else {
    $config_names = \Drupal::configFactory()->listAll();
    $choice = drush_choice($config_names, 'Choose a configuration.');
    if (empty($choice)) {
      return drush_user_abort();
    }
    else {
      $config_name = $config_names[$choice];
      $config = Drupal::configFactory()->getEditable($config_name);
    }
  }

  $active_storage = $config->getStorage();
  $contents = $active_storage->read($config_name);

  $temp_storage = new FileStorage(drush_tempdir());
  if ($file) {
    $temp_storage->write($config_name, \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file)));
    // Show difference.
    $existing = new FileStorage(drush_tempdir());
    $existing->write($config_name, $contents);
    // @todo Can DiffFormatter produce a CLI pretty diff?
    drush_shell_exec('diff -u %s %s', $existing->getFilePath($config_name), $temp_storage->getFilePath($config_name));
    $output = drush_shell_exec_output();
    drush_print(implode("\n", $output));

    if (!drush_confirm(dt('Keep these changes?'))) {
      return drush_user_abort(dt('Config not edited.'));
    }
  }
  else {
    // Write tmp YAML file for editing.
    $temp_storage->write($config_name, $contents);

    // $filepath = drush_save_data_to_temp_file();
    $exec = drush_get_editor();
    drush_shell_exec_interactive($exec, $temp_storage->getFilePath($config_name));
  }

  // Perform import operation if user did not immediately exit editor.
  if (!drush_get_option('bg', FALSE)) {
    $new_data = $temp_storage->read($config_name);
    $temp_storage->delete($config_name);
    $config->setData($new_data);
    $config->save();
  }
}

function drush_config_merge($alias = '', $config_label = 'staging') {
  // Allow the user to provide a specific working branch to do live work on.
  $working_branch = drush_get_option('branch', FALSE);
  $working_branch_is_tmp = FALSE;
  if (!$working_branch) {
    $working_branch = 'drush-live-config-temp';
    $working_branch_is_tmp = TRUE;
  }

  // Use in log and commit messages
  $site_label = $alias;
  // If '$alias' is a 'sites' folder, then convert it into a site
  // specification, root#uri
  if (($alias[0] != '@') && is_dir(DRUPAL_ROOT . '/sites/' . $alias)) {
    $alias = DRUPAL_ROOT . "#$alias";
  }

  // Figure out what our base commit is going to be for this operation.
  $merge_info = array(
    'base' => drush_get_option('base', FALSE),
    'message' => drush_get_option('message', ''),
    'commit' => !drush_get_option('no-commit', FALSE),
    'git-transport' => drush_get_option('git', FALSE),
    'remote' => drush_get_option('remote', 'origin'),
    'tool' => drush_get_option('tool', ''),
    'temp' => drush_get_option('temp', ''),
    'config-label' => $config_label,
    'live-site' => $alias,
    'dev-site' => '@self',
    'live-config' => $working_branch,
    'dev-config' => 'drush-dev-config-temp',
    'autodelete-live-config' => $working_branch_is_tmp,
    'autodelete-dev-config' => TRUE,
    'commit_needed' => FALSE,
  );

  $result = _drush_cm_get_initial_vcs_state($merge_info);
  if ($result === FALSE) {
    return FALSE;
  }
  $result = _drush_cm_prepare_for_export($merge_info);
  if ($result === FALSE) {
    return FALSE;
  }
  $result = _drush_cm_export_remote_configuration_before_merge($merge_info);
  if ($result === FALSE) {
    return FALSE;
  }

  // Copy the exported configuration from 'live-site', either via git pull or via rsync
  if ($merge_info['git-transport']) {
    $result = _drush_cm_copy_remote_configuration_via_git($merge_info);
  }
  else {
    $result = _drush_cm_copy_remote_configuration_via_rsync($merge_info);
  }
  if ($result === FALSE) {
    return FALSE;
  }

  // Exit if there were no changes from 'live-site'.
  if (empty($merge_info['changed_configuration_files'])) {
    drush_log(dt("No configuration changes on !site; nothing to do here.", array('!site' => $merge_info['live-site'])), 'ok');
    _drush_config_merge_cleanup($merge_info);
    return TRUE;
  }

  $result = _drush_cm_commit_transmitted_configuration_changes($merge_info);
  if ($result === FALSE) {
    return FALSE;
  }

  $result = _drush_cm_prepare_for_local_configuration_export($merge_info);
  if ($result === FALSE) {
    return FALSE;
  }
  $result = _drush_cm_export_local_configuration($merge_info);
  if ($result === FALSE) {
    return FALSE;
  }

  // Check to see if the export changed any files.  If it did not, then
  // skip the merge, and process only the config pulled in from the other site.
  // TODO: This needs to be a diff against the base commit.  In 'git' mode,
  // we probably want to just skip this test and always merge.  Maybe always do this?
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  $result = drush_shell_cd_and_exec($configuration_path, 'git status --porcelain .');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git status` failed."));
  }
  $changed_configuration_files = drush_shell_exec_output();
  if (empty($changed_configuration_files)) {
    drush_log(dt("No configuration changes on !site; no merge necessary.", array('!site' => $merge_info['dev-site'])), 'ok');
  }
  else {
    $result = _drush_cm_merge_local_and_remote_configurations($merge_info);
  }
  if ($result === FALSE) {
    return FALSE;
  }
  $result = _drush_cm_merge_to_original_branch($merge_info);

  return $result;
}

function _drush_cm_get_configuration_path(&$merge_info) {
  // Find the current configuration path
  if (!isset($merge_info['configuration_path'])) {
    // Get the configuration path from the local site.
    $merge_info['configuration_path'] = config_get_config_directory($merge_info['config-label']);
  }
  return $merge_info['configuration_path'];
}

function _drush_cm_get_remote_configuration_path(&$merge_info) {
  if (!isset($merge_info['remote_configuration_path'])) {
    $configdir_values = drush_invoke_process($merge_info['live-site'], 'drupal-directory', array('config-' . $merge_info['config-label']));
    $merge_info['remote_configuration_path'] = trim($configdir_values['output']);
  }
  return $merge_info['remote_configuration_path'];
}

function _drush_cm_get_initial_vcs_state(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  // Is the selected configuration directory under git revision control?  If not, fail.
  $result = drush_shell_cd_and_exec($configuration_path, 'git rev-parse --abbrev-ref HEAD');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_NO_GIT', dt("The drush config-merge command requires that the selected configuration directory !dir be under git revision control.", array('!dir' => $configuration_path)));
  }
  $output = drush_shell_exec_output();
  $original_branch = $output[0];
  drush_log(dt("Original branch is !branch", array('!branch' => $original_branch)), 'debug');
  $merge_info['original-branch'] = $original_branch;

  // Find the current sha-hash
  $result = drush_shell_cd_and_exec($configuration_path, 'git rev-parse HEAD');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_NO_GIT', dt("`git rev-parse HEAD` failed."));
  }
  $output = drush_shell_exec_output();
  $merge_info['original_hash'] = $output[0];

  // Fail if there are any uncommitted changes on the current branch
  // inside the configuration path.
  $result = drush_shell_cd_and_exec($configuration_path, 'git status --porcelain .');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git status` failed."));
  }
  $uncommitted_changes = drush_shell_exec_output();
  if (!empty($uncommitted_changes)) {
    return drush_set_error('DRUSH_CONFIG_MERGE_UNCOMMITTED_CHANGES', dt("Working set has uncommitted changes; please commit or discard them before merging.  `git stash` before `drush config-merge`, and `git stash pop` afterwards can be useful here.\n\n!changes", array('!changes' => $uncommitted_changes)));
  }
}

function _drush_cm_prepare_for_export(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  // The git transport only works if both sites have the same config path, so look up the
  // remote config path to see if this is the case, and error out if it is not.
  if ($merge_info['git-transport']) {
    $remote_configuration_path = _drush_cm_get_remote_configuration_path($merge_info);
    // n.b. $configuration_path is a relative path, whereas drupal-directory will give us
    // an absolute path.  We therefore compare only the ends of the strings.
    if ($configuration_path != substr(trim($remote_configuration_path), -strlen($configuration_path))) {
      return drush_set_error('CONFIG_MERGE_INCOMPATIBLE_PATHS', dt("The --git option only works when the configuration path is the same on the source and destination sites.  On your source site, the configuration path is !remote; on the target site, it was !local.  You must use the default transport mechanism (rsync).", array('!remote' => $configdir_values['output'], '!local' => $configuration_path)));
    }
  }

  // If the user did not supply a base commit, then we'll fill in
  // the current original hash as our base commit.
  if (!$merge_info['base']) {
    $merge_info['base'] = $merge_info['original_hash'];
  }

  // Decide how we are going to transfer the exported configuration.
  $merge_info['export_options'] = array();

  // Check to see if the user wants to use git to transfer the configuration changes;
  // if so, set up the appropriate options to pass along to config-export.
  if ($merge_info['git-transport']) {
    $merge_info['export_options']['push'] = TRUE;
    $merge_info['export_options']['remote'] = $merge_info['remote'];
    $merge_info['export_options']['branch'] = $merge_info['live-config'];
  }
  elseif ($merge_info['temp']) {
    if ($merge_info['temp'] === TRUE) {
      $merge_info['export_options']['destination'] = '%temp/config';
    }
    else {
      $merge_info['export_options']['destination'] = $merge_info['temp'];
    }
  }

  // In rsync mode, this is where we will copy from.  (Skip this assignment if
  // someone already set up or looked up the remote path.)
  if (!isset($merge_info['remote_configuration_path'])) {
    $merge_info['remote_configuration_path'] = "%config-" . $merge_info['config-label'];
  }
  $merge_info['rsync_options'] = array('delete' => TRUE);

  // Make a temporary copy of our configuration directory, so that we
  // can record what changed after calling config-export and merging.
  $merge_info['original_configuration_files'] = drush_tempdir() . '/original';
  drush_copy_dir($configuration_path, $merge_info['original_configuration_files'], FILE_EXISTS_OVERWRITE);
}

function _drush_cm_export_remote_configuration_before_merge(&$merge_info) {
  // Run config-export on the live site.
  $values = drush_invoke_process($merge_info['live-site'], 'config-export', array($merge_info['config-label']), $merge_info['export_options']);
  if ($values['error_status']) {
    return drush_set_error('DRUSH_CONFIG_MERGE_CANNOT_EXPORT', dt("Could not export configuration for site !site", array('!site' => $merge_info['live-site'])));
  }
  // After we run config-export, we remember the path to the directory
  // where the exported configuration was written.
  if (!empty($values['object']) && ($merge_info['temp'])) {
    $merge_info['remote_configuration_path'] = $values['object'];
    // $merge_info['rsync_options']['remove-source-files'] = TRUE;
  }
}

function _drush_cm_copy_remote_configuration_via_git(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  // If the config-export command worked, and exported changes, then this should
  // pull down the appropriate commit, which should change files in $configuration_path
  // (and nowhere else).
  $result = drush_shell_cd_and_exec($configuration_path, 'git pull %s %s', $merge_info['remote'], $merge_info['live-config']);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git pull` failed.  Output:\n\n!output", array('!output' => implode("\n", drush_shell_exec_output()))));
  }
  $result = drush_shell_cd_and_exec($configuration_path, 'git checkout %s', $merge_info['live-config']);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("Could not switch to working branch !b", array('!b' => $merge_info['live-config'])));
  }
  // Let's check to see if anything changed in the branch we just pulled over.
  $result = drush_shell_cd_and_exec($configuration_path, 'git diff-tree --no-commit-id --name-only -r HEAD %s .', $merge_info['original_hash']);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git diff-tree` failed."));
  }
  $merge_info['changed_configuration_files'] = drush_shell_exec_output();
}

function _drush_cm_copy_remote_configuration_via_rsync(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  $remote_configuration_path = _drush_cm_get_remote_configuration_path($merge_info);
  // Create a new temporary branch to hold the configuration changes
  // from the site 'live-config'.  The last parameter is the 'start point',
  // which is like checking out the specified sha-hash before creating the
  // branch.
  if ($merge_info['autodelete-live-config']) {
    $result = drush_shell_cd_and_exec($configuration_path, 'git checkout -B %s %s', $merge_info['live-config'], $merge_info['base']);
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("Could not create temporary branch !b", array('!b' => $merge_info['live-config'])));
    }
  }
  else {
    $result = drush_shell_cd_and_exec($configuration_path, 'git checkout -b %s', $merge_info['live-config']);
  }
  // We set the upstream branch as a service for the user, to help with
  // cleanup should this process end before completion.  We skip this if
  // the branch already existed (i.e. with --branch option).
  if ($result) {
    drush_shell_cd_and_exec($configuration_path, 'git branch --set-upstream-to=%s', $merge_info['original-branch']);
  }
  // Copy the exported configuration files from 'live-site' via rsync and commit them
  $values = drush_invoke_process($merge_info['dev-site'], 'core-rsync', array($merge_info['live-site'] . ":$remote_configuration_path/", $merge_info['dev-site'] . ":$configuration_path/"), $merge_info['rsync_options']);
  if ($values['error_status']) {
    return drush_set_error('DRUSH_CONFIG_MERGE_RSYNC_FAILED', dt("Could not rsync from !live to !dev.", array('!live' => $merge_info['live-config'], '!dev' => $merge_info['dev-config'])));
  }

  // Commit the new changes to the branch prepared for @live. Exit with
  // "nothing to do" if there are no changes to be committed.
  $result = drush_shell_cd_and_exec($configuration_path, 'git status --porcelain .');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git status` failed."));
  }
  $merge_info['changed_configuration_files'] = drush_shell_exec_output();
  $merge_info['commit_needed'] = TRUE;
}

function _drush_cm_commit_transmitted_configuration_changes(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  // Commit the files brought over via rsync.
  if ($merge_info['commit_needed']) {
    $result = drush_shell_cd_and_exec($configuration_path, 'git add -A .');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git add -A` failed."));
    }
    // Note that this commit will be `merge --squash`-ed away.  We'll put in
    // a descriptive message to help users understand where it came from, if
    // they end up with dirty branches after an aborted run.
    $result = drush_shell_cd_and_exec($configuration_path, 'git commit -m %s', 'Drush config-merge exported configuration from ' . $merge_info['live-site'] . ' ' . $merge_info['message']);
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git commit` failed."));
    }
  }
}

function _drush_cm_prepare_for_local_configuration_export(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);
  // Create a new temporary branch to hold the configuration changes
  // from the dev site ('@self').
  $result = drush_shell_cd_and_exec($configuration_path, 'git checkout -B %s %s', $merge_info['dev-config'], $merge_info['base']);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("Could not create temporary branch !b", array('!b' => $merge_info['dev-config'])));
  }
  // We set the upstream branch as a service for the user, to help with
  // cleanup should this process end before completion.
  drush_shell_cd_and_exec($configuration_path, 'git branch --set-upstream-to=%s', $merge_info['original-branch']);
}

function _drush_cm_export_local_configuration(&$merge_info) {
  // Run drush @dev cex label
  $values = drush_invoke_process($merge_info['dev-site'], 'config-export', array($merge_info['config_label']));
  if ($values['error_status']) {
    return drush_set_error('DRUSH_CONFIG_MERGE_CANNOT_EXPORT', dt("Could not export configuration for site !site", array('!site' => $merge_info['dev-site'])));
  }
}

function _drush_cm_merge_local_and_remote_configurations(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);

  $result = drush_shell_cd_and_exec($configuration_path, 'git add -A .');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git add -A` failed."));
  }
  // Note that this commit will be `merge --squash`-ed away.  We'll put in
  // a descriptive message to help users understand where it came from, if
  // they end up with dirty branches after an aborted run.
  $result = drush_shell_cd_and_exec($configuration_path, 'git commit -m %s', 'Drush config-merge exported configuration from ' . $merge_info['dev-site'] . ' ' . $merge_info['message']);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git commit` failed."));
  }

  // git checkout live-config && git rebase dev-config.
  // This will put us back on the live-config branch,
  // merge in the changes from the temporary dev branch,
  // and rebase the live-config branch to include all of
  // the commits from the dev config branch.
  $result = drush_shell_cd_and_exec($configuration_path, 'git checkout %s && git rebase %s', $merge_info['live-config'], $merge_info['dev-config']);

  // We don't need the dev-config branch any more, so we'll get rid of
  // it right away, so there is less to clean up / hang around should
  // we happen to abort before everything is done.
  if ($merge_info['autodelete-dev-config']) {
    drush_shell_cd_and_exec($configuration_path, 'git branch -D %s 2>/dev/null', $merge_info['dev-config']);
  }

  // If there are MERGE CONFLICTS: prompt the user and run 3-way diff tool.
  $result = drush_shell_cd_and_exec($configuration_path, 'git status --porcelain .', $configuration_path);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git status` failed."));
  }
  // Check to see if any line in the output starts with 'UU'.
  // This means "both sides updated" -- i.e. a conflict.
  $conflicting_configuration_changes = drush_shell_exec_output();
  $conflicting_files = array_reduce(
    $conflicting_configuration_changes,
    function($reduce, $item) use ($configuration_path) {
      if (substr($item,0,2) == "UU") {
        $reduce[] = str_replace($configuration_path . '/', '', substr($item, 3));
      }
      return $reduce;
    },
    array()
  );
  // Report on any conflicts found.
  if (!empty($conflicting_files)) {
    drush_print("\nCONFLICTS:\n");
    drush_print(implode("\n", $conflicting_files));
    drush_print("\n");
  }

  // Stop right here if the user specified --merge-only.
  if (drush_get_option('fetch-only', FALSE)) {
    drush_log(dt("Specified --fetch-only, so stopping here after the merge.  Use `git checkout !b` to return to your original branch.", array('!b' => $merge_info['original-branch'])), 'ok');
    return TRUE;
  }

  // If there are any conflicts, run the merge tool.
  if (!empty($conflicting_files)) {
    if (!$merge_info['tool'] && ($merge_info['tool'] != '')) {
      // If --tool=0, then we will never run the merge tool
      return drush_set_error('DRUSH_CONFLICTS_NOT_MERGED', dt("There were conflicts that needed merging, but mergetool disabled via --tool option.  Rolling back; run again with --fetch-only to stop prior to merge."));
    }
    $choice = 'mergetool';
    while ($choice == 'mergetool') {
      if (empty($merge_info['tool'])) {
        $result = drush_shell_cd_and_exec($configuration_path, 'git mergetool .');
      }
      else {
        $result = drush_shell_cd_and_exec($configuration_path, 'git mergetool --tool=%s .', $merge_info['tool']);
      }
      // There is no good way to tell what the result of 'git mergetool'
      // was.
      //
      // The documentation says that $result will be FALSE if the user
      // quits without saving; however, in my experience, git mergetool
      // hangs, and never returns if kdiff3 or meld exits without saving.
      //
      // We will not allow the user to continue if 'git mergetool' exits with
      // an error.  If there was no error, we will ask the user how to continue,
      // since save and exit does not necessarily mean that the user was
      // satisfied with the result of the merge.
      $done = array();
      if ($result) {
        if ($merge_info['commit']) {
          $done = array('done' => dt("All conflicts resolved!  Commit changes, re-import configuration and exit."));
        }
        else {
          $done = array('done' => dt("All conflicts resolved!  Re-import configuration and exit with unstaged changes."));
        }
      }
      $selections = $done + array(
        'abandon' => dt("Abandon merge; erase all work, and go back to original state."),
        'mergetool' => dt("Run mergetool again."),
      );
      $choice = drush_choice($selections, dt("Done with merge.  What would you like to do next?"));
      // If the user cancels, we must call drush_user_abort() for things to work right.
      if ($choice === FALSE) {
        return drush_user_abort();
      }
      // If there is an action function, then call it.
      $fn = '_drush_config_merge_action_' . $choice;
      if (function_exists($fn)) {
        $choice = $fn($merge_info);
      }
      // If the action function returns TRUE or FALSE, then
      // return with that result without taking further action.
      if (is_bool($choice)) {
        return $choice;
      }
    }
    // Commit the results of the merge to the working branch.  This
    // commit will be squash-merged with the others below; if the
    // --no-commit option was selected, the results of the squash-merge
    // will remain unstaged.
    $result = drush_shell_cd_and_exec($configuration_path, 'git add -A .');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git add -A` failed."));
    }
    $result = drush_shell_cd_and_exec($configuration_path, 'git commit -m %s', 'Drush config-merge merge commit for ' . $merge_info['live-site']. ' configuration with ' . $merge_info['dev-site'] . ' configuration.');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git commit` failed."));
    }
  }
}

function _drush_cm_merge_to_original_branch(&$merge_info) {
  $configuration_path = _drush_cm_get_configuration_path($merge_info);

  // Merge the results of the 3-way merge back to the original branch.
  drush_shell_cd_and_exec($configuration_path, 'git checkout %s', $merge_info['original-branch']);
  // Run 'git merge' and 'git commit' as separate operations, as 'git merge --squash'
  // seems to ignore the --commit option.
  $result = drush_shell_cd_and_exec($configuration_path, 'git merge --no-commit --squash %s', $merge_info['live-config']);
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git merge --squash` failed.  Output:\n\n!output", array('!output' => implode("\n", drush_shell_exec_output()))));
  }

  // Re-import the merged changes into the database for the local site.
  drush_set_option('strict', 0);
  $result = drush_invoke('config-import', array($merge_info['config_label']));
  if ($result === FALSE) {
    // If there was an error, or nothing to import, return FALSE,
    // signaling rollback.
    return FALSE;
  }

  // Check to see if the merge resulted in any changed files.
  // If there were no changes in dev, then there might not be
  // anything to do here.
  $result = drush_shell_cd_and_exec($configuration_path, 'git status --porcelain .');
  if (!$result) {
    return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git status` failed."));
  }
  $files_changed_by_merge = drush_shell_exec_output();

  // If there were any files changed in the merge, then import them and commit.
  if (!empty($files_changed_by_merge)) {
    if ($merge_info['commit']) {
      if (empty($merge_info['message'])) {
        // The 'dev-site' is probably just '@self', so we'll put the site-name
        // in the comment, which hopefully will read okay
        $config = Drupal::config('system.site');
        $site_name = $config->get('name');
        $merge_info['message'] = dt("Merged configuration from !live in !site", array('!live' => $merge_info['live-site'], '!site' => $site_name));

        // Retrieve a list of differences between the active and target configuration (if any).
        $target_storage = new FileStorage($configuration_path);
        /** @var \Drupal\Core\Config\StorageInterface $active_storage */
        $active_storage = new FileStorage($merge_info['original_configuration_files']);
        $config_comparer = new StorageComparer($active_storage, $target_storage, Drupal::service('config.manager'));
        if ($config_comparer->createChangelist()->hasChanges()) {
          $change_list = array();
          foreach ($config_comparer->getAllCollectionNames() as $collection) {
            $change_list[$collection] = $config_comparer->getChangelist(NULL, $collection);
          }
          $tbl = _drush_format_config_changes_table($change_list);
          $output = $tbl->getTable();
          if (!stristr(PHP_OS, 'WIN')) {
            $output = str_replace("\r\n", PHP_EOL, $output);
          }
          $merge_info['message'] .= "\n\n$output";
        }
      }
      $comment_file = drush_save_data_to_temp_file($merge_info['message']);
      $result = drush_shell_cd_and_exec($configuration_path, 'git commit --file=%s', $comment_file);
      if (!$result) {
        return drush_set_error('DRUSH_CONFIG_MERGE_FAILURE', dt("`git commit` failed."));
      }
    }
  }
  _drush_config_merge_cleanup($merge_info);
  return TRUE;
}

/**
 * If drush_config_merge() exits with an error, then Drush will
 * call the rollback function, so that we can clean up.  We call
 * the cleanup function explicitly if we exit with no error.
 */
function drush_config_merge_rollback() {
  _drush_config_merge_cleanup(drush_get_context('DRUSH_CONFIG_MERGE_INFO'));
}

/**
 * If the user wants to abandon the work of their merge, then
 * clean up our temporary branches and return TRUE to cause
 * the calling function to exit without committing.
 */
function _drush_config_merge_action_abandon(&$merge_info) {
  _drush_config_merge_cleanup($merge_info);
  drush_log(dt("All changes erased."), 'ok');
  return TRUE;
}

/* Helper functions */

/**
 * Reset our state after a config-merge command
 */
function _drush_config_merge_cleanup($merge_info) {
  if (!empty($merge_info) && !empty($merge_info['configuration_path'])) {
    $configuration_path = $merge_info['configuration_path'];
    // If we are in the middle of a rebase, we must abort, or
    // git will remember this state for a long time (that is,
    // you can switch away from this branch and come back later,
    // and you'll still be in a "rebasing" state.)
    drush_shell_cd_and_exec($configuration_path, 'git rebase --abort');
    // Violently delete any untracked files in the configuration path
    // without prompting.  This isn't as dangerous as it sounds;
    // drush config-merge refuses to run if you have untracked files
    // here, and you can get anything that Drush config-merge put here
    // via `drush cex` (or just run config-merge again).
    drush_shell_cd_and_exec($configuration_path, 'git clean -d -f .');
    // Switch back to the branch we started on.
    $result = drush_shell_cd_and_exec($configuration_path, 'git checkout %s', $merge_info['original-branch']);
    if (!$result) {
      drush_log(dt("Could not return to original branch !branch", array('!branch' => $merge_info['original-branch'])), 'warning');
    }
    // Delete our temporary branches
    if ($merge_info['autodelete-live-config']) {
      drush_shell_cd_and_exec($configuration_path, 'git branch -D %s 2>/dev/null', $merge_info['live-config']);
    }
    if ($merge_info['autodelete-dev-config']) {
      drush_shell_cd_and_exec($configuration_path, 'git branch -D %s 2>/dev/null', $merge_info['dev-config']);
    }
  }
}

/**
 * Show and return a config object
 *
 * @param $config_name
 *   The config object name.
 */
function drush_config_get_object($config_name) {
  $source = drush_get_option('source', 'active');
  $include_overridden = drush_get_option('include-overridden', FALSE);

  if ($include_overridden) {
    // Displaying overrides only applies to active storage.
    $config = \Drupal::config($config_name);
    $data = $config->get();
  }
  elseif ($source == 'active') {
    $config = \Drupal::service('config.storage');
    $data = $config->read($config_name);
  }
  elseif ($source == 'staging') {
    $config = \Drupal::service('config.storage.staging');
    $data = $config->read($config_name);
  }
  else {
    return drush_set_error(dt('Unknown value !value for config source.', array('!value' => $source)));
  }

  if ($data === FALSE) {
    return drush_set_error(dt('Config !name does not exist in !source configuration.', array('!name' => $config_name, '!source' => $source)));
  }
  if (empty($data)) {
    drush_log(dt('Config !name exists but has no data.', array('!name' => $config_name)), 'notice');
    return;
  }
  return $data;
}

/**
 * Show and return a value from config system.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 */
function drush_config_get_value($config_name, $key) {
  $config = Drupal::config($config_name);
  if ($config->isNew()) {
    return drush_set_error(dt('Config !name does not exist', array('!name' => $config_name)));
  }
  $value = $config->get($key);
  $returns[$config_name . ':' . $key] = $value;

  if ($value === NULL) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No matching key found in !name config.', array('!name' => $config_name)));
  }
  else {
    return $returns;
  }
}

/**
 * Print a table of config changes.
 *
 * @param array $config_changes
 *   An array of changes keyed by collection.
 */
function _drush_format_config_changes_table(array $config_changes, $use_color = FALSE) {
  if (!$use_color) {
    $red = "%s";
    $yellow = "%s";
    $green = "%s";
  }
  else {
    $red = "\033[31;40m\033[1m%s\033[0m";
    $yellow = "\033[1;33;40m\033[1m%s\033[0m";
    $green = "\033[1;32;40m\033[1m%s\033[0m";
  }

  $rows = array();
  $rows[] = array('Collection', 'Config', 'Operation');
  foreach ($config_changes as $collection => $changes) {
    foreach ($changes as $change => $configs) {
      switch ($change) {
        case 'delete':
          $colour = $red;
          break;
        case 'update':
          $colour = $yellow;
          break;
        case 'create':
          $colour = $green;
          break;
        default:
          $colour = "%s";
          break;
      }
      foreach($configs as $config) {
        $rows[] = array(
          $collection,
          $config,
          sprintf($colour, $change)
        );
      }
    }
  }
  $tbl = _drush_format_table($rows);
  return $tbl;
}

/**
 * Print a table of config changes.
 *
 * @param array $config_changes
 *   An array of changes keyed by collection.
 */
function _drush_print_config_changes_table(array $config_changes) {
  $tbl =  _drush_format_config_changes_table($config_changes, !drush_get_context('DRUSH_NOCOLOR'));

  $output = $tbl->getTable();
  if (!stristr(PHP_OS, 'WIN')) {
    $output = str_replace("\r\n", PHP_EOL, $output);
  }

  drush_print(rtrim($output));
  return $tbl;
}

/**
 * Command argument complete callback.
 */
function config_config_get_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_set_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_view_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_edit_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_import_complete() {
  return _drush_config_directories_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_export_complete() {
  return _drush_config_directories_complete();
}

/**
 * Helper function for command argument complete callback.
 *
 * @return
 *   Array of available config directories.
 */
function _drush_config_directories_complete() {
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  global $config_directories;
  return array('values' => array_keys($config_directories));
}

/**
 * Helper function for command argument complete callback.
 *
 * @return
 *   Array of available config names.
 */
function _drush_config_names_complete() {
  drush_bootstrap_max();
  return array('values' => $storage = \Drupal::service('config.storage')->listAll());
}
