<?php
namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Config\FileStorage;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Yaml\Parser;

class ConfigCommands extends DrushCommands {

  /**
   * Display a config value, or a whole configuration object.
   *
   * @command config-get
   * @validate-config-name
   * @interact-config-name
   * @param $config_name The config object name, for example "system.site".
   * @param $key The config key, for example "page.front". Optional.
   * @option source The config storage source to read. Additional labels may be defined in settings.php.
   * @option include-overridden Apply module and settings.php overrides to values.
   * @usage drush config-get system.site
   *   Displays the system.site config.
   * @usage drush config-get system.site page.front
   *   Gets system.site:page.front value.
   * @aliases cget
   * @complete \Drush\Commands\core\ConfigCommands::completeNames
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function get($config_name, $key = '', $options = ['format' => 'yaml', 'source' => 'active', 'include-overridden' => FALSE]) {
    // Displaying overrides only applies to active storage.
    $factory = \Drupal::configFactory();
    $config = $options['include-overridden'] ? $factory->getEditable($config_name) : $factory->get($config_name);
    $value = $config->get($key);
    // @todo If the value is TRUE (for example), nothing gets printed. Is this yaml formatter's fault?
    return $key ? ["$config_name:$key" => $value] : $value;
  }

  /**
   * Set config value directly. Does not perform a config import.
   *
   * @command config-set
   * @validate-config-name
   * @todo @interact-config-name deferred until we have interaction for key.
   * @param $config_name The config object name, for example "system.site".
   * @param $key The config key, for example "page.front".
   * @param $value The value to assign to the config key. Use '-' to read from STDIN.
   * @option format Format to parse the object. Use "string" for string (default), and "yaml" for YAML.
   * // A convenient way to pass a multiline value within a backend request.
   * @option value The value to assign to the config key (if any).
   * @hidden-options value
   * @usage drush config-set system.site page.front node
   *   Sets system.site:page.front to "node".
   * @aliases cset
   * @complete \Drush\Commands\core\ConfigCommands::completeNames
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function set($config_name, $key, $value = NULL, $options = ['format' => 'string', 'value' => NULL]) {
    // This hidden option is a convenient way to pass a value without passing a key.
    $data = $options['value'] ?: $value;

    if (!isset($data)) {
      throw new \Exception(dt('No config value specified.'));
    }

    $config = \Drupal::configFactory()->getEditable($config_name);
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
    switch ($options['format']) {
      case 'yaml':
        $parser = new Parser();
        $data = $parser->parse($data, TRUE);
    }

    if (is_array($data) && $this->io()->confirm(dt('Do you want to update or set multiple keys on !name config.', array('!name' => $config_name)))) {
      foreach ($data as $key => $value) {
        $config->set($key, $value);
      }
      return $config->save();
    }
    else {
      $confirmed = FALSE;
      if ($config->isNew() && $this->io()->confirm(dt('!name config does not exist. Do you want to create a new config object?', array('!name' => $config_name)))) {
        $confirmed = TRUE;
      }
      elseif ($new_key && $this->io()->confirm(dt('!key key does not exist in !name config. Do you want to create a new config key?', array('!key' => $key, '!name' => $config_name)))) {
        $confirmed = TRUE;
      }
      elseif ($this->io()->confirm(dt('Do you want to update !key key in !name config?', array('!key' => $key, '!name' => $config_name)))) {
        $confirmed = TRUE;
      }
      if ($confirmed && !drush_get_context('DRUSH_SIMULATE')) {
        return $config->set($key, $data)->save();
      }
    }
  }

  /**
   * Open a config file in a text editor. Edits are imported after closing editor.
   *
   * @command config-edit
   * @validate-config-name
   * @interact-config-name
   * @param $config_name The config object name, for example "system.site".
   * @optionset_get_editor
   * @allow_additional_options config-import
   * @hidden-options source,partial
   * @usage drush config-edit image.style.large
   *   Edit the image style configurations.
   * @usage drush config-edit
   *   Choose a config file to edit.
   * @usage drush config-edit --choice=2
   *   Edit the second file in the choice list.
   * @usage drush --bg config-edit image.style.large
   *   Return to shell prompt as soon as the editor window opens.
   * @aliases cedit
   * @validate-module-enabled config
   * @complete \Drush\Commands\core\ConfigCommands::completeNames
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function edit($config_name, $options = []) {
    $config = \Drupal::configFactory()->get($config_name);
    $active_storage = $config->getStorage();
    $contents = $active_storage->read($config_name);

    // Write tmp YAML file for editing
    $temp_dir = drush_tempdir();
    $temp_storage = new FileStorage($temp_dir);
    $temp_storage->write($config_name, $contents);

    $exec = drush_get_editor();
    drush_shell_exec_interactive($exec, $temp_storage->getFilePath($config_name));

    // Perform import operation if user did not immediately exit editor.
    if (!$options['bg']) {
      $options = drush_redispatch_get_options() + array('partial' => TRUE, 'source' => $temp_dir);
      $backend_options = array('interactive' => TRUE);
      return (bool) drush_invoke_process('@self', 'config-import', array(), $options, $backend_options);
    }
  }

  /**
   * Delete a configuration object.
   *
   * @command config-delete
   * @validate-config-name
   * @interact-config-name
   * @param $config_name The config object name, for example "system.site".
   * @aliases cdel
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @complete \Drush\Commands\core\ConfigCommands::completeNames
   */
  public function delete($config_name, $options = []) {
    $config =\Drupal::service('config.factory')->getEditable($config_name);
    if ($config->isNew()) {
      throw new \Exception('Configuration name not recognized.');
    }
    else {
      $config->delete();
    }
  }

  /**
   * @hook validate config-pull
   */
  function validateConfigPull(CommandData $commandData) {
    if ($commandData->input()->getOption('safe')) {
      $return = drush_invoke_process($commandData->input()->getArgument('destination'), 'core-execute', array('git diff --quiet'), array('escape' => 0));
      if ($return['error_status']) {
        throw new \Exception('There are uncommitted changes in your git working copy.');
      }
    }
  }

  /**
   * Export and transfer config from one environment to another.
   *
   * @command config-pull
   * @param string $source A site-alias or the name of a subdirectory within /sites whose config you want to copy from,
   * @param string $destination A site-alias or the name of a subdirectory within /sites whose config you want to replace.
   * @option safe Validate that there are no git uncommitted changes before proceeding
   * @option label A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync'
   * @option runner Where to run the rsync command; defaults to the local site. Can also be 'source' or 'destination'
   * @usage drush config-pull @prod @stage
   *   Export config from @prod and transfer to @stage.
   * @usage drush config-pull @prod @self --label=vcs
   *   Export config from @prod and transfer to the 'vcs' config directory of current site.
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @aliases cpull
   * @complete \Drush\Commands\CompletionCommands::completeSiteAliases
   * @topics docs-aliases,docs-config-exporting
   *
   */
  function pull($source, $destination, $options = ['safe' => FALSE, 'label' => 'sync', 'runner' => NULL]) {
    // @todo drush_redispatch_get_options() assumes you will execute same command. Not good.
    $global_options = drush_redispatch_get_options() + array(
      'strict' => 0,
    );

    // @todo If either call is made interactive, we don't get an $return['object'] back.
    $backend_options = array('interactive' => FALSE);
    if (drush_get_context('DRUSH_SIMULATE')) {
      $backend_options['backend-simulate'] = TRUE;
    }

    $export_options = array(
      // Use the standard backup directory on Destination.
      'destination' => TRUE,
      'yes' => NULL,
    );
    $this->logger()->notice(dt('Starting to export configuration on Target.'));
    $return = drush_invoke_process($source, 'config-export', array(), $global_options + $export_options, $backend_options);
    if ($return['error_status']) {
      throw new \Exception(dt('Config-export failed.'));
    }
    else {
      // Trailing slash assures that transfer files and not the containing dir.
      $export_path = $return['object'] . '/';
    }

    $rsync_options = array(
      '--remove-source-files',
      '--delete',
      '--exclude=.htaccess',
    );
    $label = $options['label'];
    $runner = drush_get_runner($source, $destination, drush_get_option('runner', FALSE));
    $this->logger()->notice(dt('Starting to rsync configuration files from !source to !dest.', array('!source' => $source, '!dest' => $destination)));
    // This comment applies similarly to sql-sync's use of core-rsync.
    // Since core-rsync is a strict-handling command and drush_invoke_process() puts options at end, we can't send along cli options to rsync.
    // Alternatively, add options like --ssh-options to a site alias (usually on the machine that initiates the sql-sync).
    $return = drush_invoke_process($runner, 'core-rsync', array_merge(["$source:$export_path", "$destination:%config-$label", '--'], $rsync_options), ['yes' => TRUE], $backend_options);
    if ($return['error_status']) {
      throw new \Exception(dt('Config-pull rsync failed.'));
    }

    drush_backend_set_result($return['object']);
  }

  /**
   * Build a table of config changes.
   *
   * @param array $config_changes
   *   An array of changes keyed by collection.
   */
  public static function configChangesTableFormat(array $config_changes, $use_color = FALSE) {
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
  public static function configChangesTablePrint(array $config_changes) {
    $tbl =  self::configChangesTableFormat($config_changes, !drush_get_context('DRUSH_NOCOLOR'));

    $output = $tbl->getTable();
    if (!stristr(PHP_OS, 'WIN')) {
      $output = str_replace("\r\n", PHP_EOL, $output);
    }

    drush_print(rtrim($output));
    return $tbl;
  }

  /**
   * @hook interact @interact-config-name
   */
  public function interactConfigName($input, $output) {
    if (empty($input->getArgument('config_name'))) {
      $config_names = \Drupal::configFactory()->listAll();
      $choice = $this->io()->choice('Choose a configuration', drush_map_assoc($config_names));
      $input->setArgument('config_name', $choice);
    }
  }

  /**
   * @hook interact @interact-config-label
   */
  public function interactConfigLabel(InputInterface $input, ConsoleOutputInterface $output) {
    global $config_directories;

    $option_name = $input->hasOption('destination') ? 'destination' : 'source';
    if (empty($input->getArgument('label') && empty($input->getOption($option_name)))) {
      $choices = drush_map_assoc(array_keys($config_directories));
      unset($choices[CONFIG_ACTIVE_DIRECTORY]);
      if (count($choices) >= 2) {
        $label = $this->io()->choice('Choose a '. $option_name. '.', $choices);
        $input->setArgument('label', $label);
      }
    }
  }

  /**
   * Validate that a config name is valid.
   *
   * If the argument to be validated is not named $config_name, pass the
   * argument name as the value of the annotation.
   *
   * @hook validate @validate-config-name
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateConfigName(CommandData $commandData) {
    $arg_name = $commandData->annotationData()->get('validate-config-name', NULL) ?: 'config_name';
    $config_name = $commandData->input()->getArgument($arg_name);
    $config = \Drupal::config($config_name);
    if ($config->isNew()) {
      $msg = dt('Config !name does not exist', array('!name' => $config_name));
      return new CommandError($msg);
    }
  }

  public function completeNames() {
    // @todo This is not bootstrapping. Neither is drush_complete_rebuild_arguments().
    drush_bootstrap_max();
    return array('values' => \Drupal::service('config.storage')->listAll());
  }

  function completeLabels() {
    // @todo This is not bootstrapping. Neither is drush_complete_rebuild_arguments().
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
    global $config_directories;
    return array('values' => array_keys($config_directories));
  }
}
