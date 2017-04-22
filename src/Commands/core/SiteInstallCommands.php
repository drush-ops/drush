<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Log\LogLevel;
use Drupal\Core\Config\FileStorage;
use Drush\Sql\SqlBase;

class SiteInstallCommands extends DrushCommands {

  /**
   * Install Drupal along with modules/themes/configuration/profile.
   *
   * @command site-install
   * @param $profile An install profile name. Defaults to 'standard' unless an install profile is marked as a distribution.
   * @param $additional Additional info for the install profile. The key is in the form [form name].[parameter name]
   * @option db-url A Drupal 6 style database URL. Required for initial install, not re-install. If omitted and required, Drush prompts for this item.
   * @option db-prefix An optional table prefix to use for initial install.
   * @option db-su Account to use when creating a new database. Must have Grant permission (mysql only). Optional.
   * @option db-su-pw Password for the "db-su" account. Optional.
   * @option account-name uid1 name. Defaults to admin
   * @option account-pass uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in drushrc.php.
   * @option account-mail uid1 email. Defaults to admin@example.com
   * @option locale A short language code. Sets the default site language. Language files must already be present.
   * @option site-name Defaults to Site-Install
   * @option site-mail From: for system mailings. Defaults to admin@example.com
   * @option sites-subdir Name of directory under 'sites' which should be created.
   * @option config-dir A path pointing to a full set of configuration which should be imported after installation.
   * @usage drush site-install expert --locale=uk
   *   (Re)install using the expert install profile. Set default language to Ukrainian.
   * @usage drush site-install --db-url=mysql://root:pass@localhost:port/dbname
   *   Install using the specified DB params.
   * @usage drush site-install --db-url=sqlite://sites/example.com/files/.ht.sqlite
   *   Install using SQLite
   * @usage drush site-install --account-name=joe --account-pass=mom
   *   Re-install with specified uid1 credentials.
   * @usage drush si install_configure_form.site_default_country=FR
   *   Pass additional arguments to the profile (D7 example shown here.
   * @usage drush si install_configure_form.update_status_module='array(FALSE,FALSE)'
   *   Disable email notification during install and later. If server has no smtp, this avoids an error.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   * @aliases si
   *
   */
  public function install($profile, array $additional, $options = ['db-url' => NULL, 'db-prefix' => NULL, 'db-su' => NULL, 'db-su-pw' => NULL, 'account-name' => 'admin', 'account-mail' => 'admin@example.com', 'site-mail' => 'admin@example.com', 'account-pass' => NULL, 'locale' => 'en', 'site-name' => 'Drush Site-Install', 'site-pass' => NULL, 'sites-subdir' => NULL, 'config-dir' => NULL]) {
    $form_options = [];
    foreach ((array)$additional as $arg) {
      list($key, $value) = explode('=', $arg, 2);

      // Allow for numeric and NULL values to be passed in.
      if (is_numeric($value)) {
        $value = intval($value);
      }
      elseif ($value == 'NULL') {
        $value = NULL;
      }

      $form_options[$key] = $value;
    }

    $class_loader = drush_drupal_load_autoloader(DRUPAL_ROOT);
    $profile = $this->determineProfile($profile, $options, $class_loader);

    $sql = SqlBase::create($options);
    $db_spec = $sql->getDbSpec();

    $show_password = empty($options['account-pass']);
    $account_pass = $options['account-pass'] ?: drush_generate_password();
    $settings = array(
      'parameters' => array(
        'profile' => $profile,
        'langcode' => $options['locale'],
      ),
      'forms' => array(
        'install_settings_form' => array(
          'driver' => $db_spec['driver'],
          $db_spec['driver'] => $db_spec,
          'op' => dt('Save and continue'),
        ),
        'install_configure_form' => array(
          'site_name' => $options['site-name'],
          'site_mail' => $options['site-mail'],
          'account' => array(
            'name' => $options['account-name'],
            'mail' => $options['account-mail'],
            'pass' => array(
              'pass1' => $account_pass,
              'pass2' => $account_pass,
            ),
          ),
          'update_status_module' => array(
            1 => TRUE,
            2 => TRUE,
          ),
          'clean_url' => TRUE,
          'op' => dt('Save and continue'),
        ),
      ),
    );

    // Merge in the additional options.
    foreach ($form_options as $key => $value) {
      $current = &$settings['forms'];
      foreach (explode('.', $key) as $param) {
        $current = &$current[$param];
      }
      $current = $value;
    }

    $msg = 'Starting Drupal installation. This takes a while.';
    // @todo Check if this option gets passed.
    if (is_null($options['notify'])) {
      $msg .= ' Consider using the --notify global option.';
    }
    $this->logger()->info(dt($msg));
    require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';
    drush_op('install_drupal', $class_loader, $settings);
    if ($show_password) {
      $this->logger()->success(dt('Installation complete.  User name: @name  User password: @pass', array('@name' => $options['account-name'], '@pass' => $account_pass)));
    }
    else {
      $this->logger()->success(dt('Installation complete.'));
    }
  }

  function determineProfile($profile, $options, $class_loader) {
    // --config-dir fails with Standard profile and any other one that carries content entities.
    // Force to minimal install profile.
    if ($options['config-dir']) {
      $this->logger()->info(dt("Using 'minimal' install profile since --config-dir option was provided."));
      $profile = 'minimal';
    }
    elseif(!isset($profile)) {
      $profile = drupal_get_profile() ?: 'standard';
    }
    return $profile;
  }

  /**
   * Post installation, run the configuration import.
   *
   * @hook post-command site-install
   */
  public function post($result, CommandData $commandData) {
    if ($config = $commandData->input()->getOption('config-dir')) {
      // Set the destination site UUID to match the source UUID, to bypass a core fail-safe.
      $source_storage = new FileStorage($config);
      $options = ['yes' => TRUE];
      drush_invoke_process('@self', 'config-set', array('system.site', 'uuid', $source_storage->read('system.site')['uuid']), $options);
      // Run a full configuration import.
      drush_invoke_process('@self', 'config-import', array(), array('source' => $config) + $options);
    }
  }
  /**
   * Check to see if there are any .yml files in the provided config directory.
   */
  protected function hasConfigFiles($config) {
    $files = glob("$config/*.yml");
    return !empty($files);
  }
  /**
   * @hook validate site-install
   */
  public function validate(CommandData $commandData) {
    if ($sites_subdir = $commandData->input()->getOption('sites-subdir')) {
      $lower = strtolower($sites_subdir);
      if ($sites_subdir != $lower) {
        $this->logger()->warning(dt('Only lowercase sites-subdir are valid. Switching to !lower.', array('!lower' => $lower)));
        $commandData->input()->setOption('sites-subdir', $lower);
      }
      // Make sure that we will bootstrap to the 'sites-subdir' site.
      drush_set_context('DRUSH_SELECTED_URI', 'http://' . $sites_subdir);
    }

    if ($config = $commandData->input()->getOption('config-dir')) {
      if (!file_exists($config)) {
        throw new \Exception('The config source directory does not exist.');
      }
      if (!is_dir($config)) {
        throw new \Exception('The config source is not a directory.');
      }
      // Skip config import with a warning if specified config dir is empty.
      if (!$this->hasConfigFiles($config)) {
        $this->logger()->warning(dt('Configuration import directory @config does not contain any configuration; will skip import.', ['@config' => $config]));
        $commandData->input()->setOption('config-dir', '');
      }
    }

    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
    try {
      $sql = SqlBase::create($commandData->input()->getOptions());
    }
    catch (\Exception $e) {
      // Ask questions to get our data.
      if ($commandData->input()->getOption('db-url') == '') {
        // Prompt for the db-url data if it was not provided via --db-url.
        $database = $this->io()->ask('Database name', 'drupal');
        $driver = $this->io()->ask('Database driver', 'mysql');
        $username = $this->io()->ask('Database username', 'drupal');
        $password = $this->io()->ask('Database password', 'drupal');
        $host = $this->io()->ask('Database host', '127.0.0.1');
        $port = $this->io()->ask('Database port', '3306');
        $db_url = "$driver://$username:$password@$host:$port/$database";
        $commandData->input()->setOption('db-url', $db_url);

        try {
          $sql = SqlBase::create($commandData->input()->getOptions());
        }
        catch (\Exception $e) {
          throw new \Exception(dt('Could not determine database connection parameters. Pass --db-url option.'));
        }
      }
    }
    if (!$sql->getDbSpec()) {

    }
  }

  /**
   * Perform setup tasks before installation.
   *
   * @hook pre-command site-install
   *
   */
  public function pre(CommandData $commandData) {
    $sql = SqlBase::create($commandData->input()->getOptions());
    $db_spec = $sql->getDbSpec();

    // Make sure URI is set so we get back a proper $alias_record. Needed for quick-drupal.
    _drush_bootstrap_selected_uri();

    $alias_record = drush_sitealias_get_record('@self');
    $sites_subdir = drush_sitealias_local_site_path($alias_record);
    // Override with sites-subdir if specified.
    if ($dir = $commandData->input()->getOption('sites-subdir')) {
      $sites_subdir = "sites/$dir";
    }
    $conf_path = $sites_subdir;
    $settingsfile = "$conf_path/settings.php";
    $sitesfile = "sites/sites.php";
    $default = realpath($alias_record['root'] . '/sites/default');
    $sitesfile_write = $conf_path != $default && !file_exists($sitesfile);

    if (!file_exists($settingsfile)) {
      $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
    }
    if ($sitesfile_write) {
      $msg[] = dt('create a @sitesfile file', array('@sitesfile' => $sitesfile));
    }
    if ($sql->dbExists()) {
      $msg[] = dt("DROP all tables in your '@db' database.", array('@db' => $db_spec['database']));
    }
    else {
      $msg[] = dt("CREATE the '@db' database.", array('@db' => $db_spec['database']));
    }

    if (!$this->io()->confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
      throw new UserAbortException();
    }

    // Can't install without sites subdirectory and settings.php.
    if (!file_exists($conf_path)) {
      if (!drush_mkdir($conf_path) && !drush_get_context('DRUSH_SIMULATE')) {
        throw new \Exception(dt('Failed to create directory @conf_path', array('@conf_path' => $conf_path)));
      }
    }
    else {
      $this->logger()->info(dt('Sites directory @subdir already exists - proceeding.', array('@subdir' => $conf_path)));
    }

    if (!drush_file_not_empty($settingsfile)) {
      if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile) && !drush_get_context('DRUSH_SIMULATE')) {
        throw new \Exception(dt('Failed to copy sites/default/default.settings.php to @settingsfile', array('@settingsfile' => $settingsfile)));
      }
    }

    // Write an empty sites.php if we using multi-site.
    if ($sitesfile_write) {
      if (!drush_op('copy', 'sites/example.sites.php', $sitesfile) && !drush_get_context('DRUSH_SIMULATE')) {
        throw new \Exception(dt('Failed to copy sites/example.sites.php to @sitesfile', array('@sitesfile' => $sitesfile)));
      }
    }

    // We need to be at least at DRUSH_BOOTSTRAP_DRUPAL_SITE to select the site uri to install to
    define('MAINTENANCE_MODE', 'install');
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);

    if (!$sql->dropOrCreate()) {
      throw new \Exception(dt('Failed to create database: @error', array('@error' => implode(drush_shell_exec_output()))));
    }
  }
}
