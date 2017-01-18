<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;
use Drupal\Core\Config\FileStorage;

class SiteInstallCommands extends DrushCommands {

  /**
   * Install Drupal along with modules/themes/configuration using the specified install profile.
   *
   * @command site-install
   * @param string $profile The install profile you wish to run. Defaults to 'default' in D6, 'standard' in D7+, unless an install profile is marked as exclusive (or as a distribution in D8+ terminology) in which case that is used.
   * @param $additional Any additional settings you wish to pass to the profile. The key is in the form [form name].[parameter name]
   * @option db-url A Drupal 6 style database URL. Only required for initial install - not re-install.
   * @option db-prefix An optional table prefix to use for initial install.  Can be a key-value array of tables/prefixes in a drushrc file (not the command line).
   * @option db-su Account to use when creating a new database. Must have Grant permission (mysql only). Optional.
   * @option db-su-pw Password for the "db-su" account. Optional.
   * @option account-name uid1 name. Defaults to admin
   * @option account-pass uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in drushrc.php.
   * @option account-mail uid1 email. Defaults to admin@example.com
   * @option locale A short language code. Sets the default site language. Language files must already be present.
   * @option site-name Defaults to Site-Install
   * @option site-mail From: for system mailings. Defaults to admin@example.com
   * @option sites-subdir Name of directory under 'sites' which should be created. Only needed when the subdirectory does not already exist. Defaults to 'default'
   * @option config-dir A path pointing to a full set of configuration which should be imported after installation.
   * @usage drush site-install expert --locale=uk
   *   (Re)install using the expert install profile. Set default language to Ukrainian.
   * @usage drush site-install --db-url=mysql://root:pass@localhost:port/dbname
   *   Install using the specified DB params.
   * @usage drush site-install --db-url=sqlite://sites/example.com/files/.ht.sqlite
   *   Install using SQLite
   * @usage drush site-install --account-name=joe --account-pass=mom
   *   Re-install with specified uid1 credentials.
   * @usage drush site-install standard install_configure_form.site_default_country=FR my_profile_form.my_settings.key=value
   *   Pass additional arguments to the profile (D7 example shown here.
   * @usage drush site-install standard install_configure_form.update_status_module='array(FALSE,FALSE)'
   *   Disable email notification during install and later. If your server has no smtp, this gets rid of an error during install.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_ROOT
   * @aliases si
   *
   * @todo cast $additional to an array to get variable argument handling
   */
  public function install($profile, $additional = NULL, $options = ['db-url' => NULL, 'db-prefix' => NULL, 'db-su' => NULL, 'db-su-pw' => NULL, 'account-name' => 'admin', 'account-mail' => 'admin@example.com', 'account-pass' => NULL, 'locale' => 'en', 'site-name' => 'Drush Site-Install', 'site-pass' => NULL, 'sites-subdir' => NULL, 'config-dir' => NULL]) {
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

    $sql = drush_sql_get_class();
    $db_spec = $sql->db_spec();

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
    drush_op('install_drupal', $class_loader, $settings);
    $this->logger()->success(dt('Installation complete.  User name: @name  User password: @pass', array('@name' => $options['account-name'], '@pass' => $account_pass)));
  }

  function determineProfile($profile, $options, $class_loader) {
    // --config-dir fails with Standard profile and any other one that carries content entities.
    // Force to minimal install profile.
    if ($options['config-dir']) {
      $this->logger()->info(dt("Using 'minimal' install profile since --config-dir option was provided."));
      $profile = 'minimal';
    }
    else {
      require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';

      if (!isset($profile)) {
        // If there is an installation profile that acts as a distribution, use it.
        // You can turn your installation profile into a distribution by providing a
        // @code
        //   distribution:
        //     name: 'Distribution name'
        // @endcode
        // block in the profile's info YAML file.
        // See https://www.drupal.org/node/2210443 for more information.
        $install_state = array('interactive' => FALSE) + install_state_defaults();
        try {
          install_begin_request($class_loader, $install_state);
          $profile = _install_select_profile($install_state);
        } catch (\Exception $e) {
          // This is only a best effort to provide a better default, no harm done
          // if it fails.
        }
        if (empty($profile)) {
          $profile = 'standard';
        }
      }
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
    }

    $sql = drush_sql_get_class();
    if (!$sql->db_spec()) {
      throw new \Exception(dt('Could not determine database connection parameters. Pass --db-url option.'));
    }
  }

  /**
   * Perform setup tasks before installation.
   *
   * @hook pre-command site-install
   *
   */
  public function pre(CommandData $commandData) {
    $sql = drush_sql_get_class();
    $db_spec = $sql->db_spec();

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
    if ($sql->db_exists()) {
      $msg[] = dt("DROP all tables in your '@db' database.", array('@db' => $db_spec['database']));
    }
    else {
      $msg[] = dt("CREATE the '@db' database.", array('@db' => $db_spec['database']));
    }

    if (!drush_confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
      // @todo test this.
      return drush_user_abort();
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

    if (!$sql->drop_or_create()) {
      throw new \Exception(dt('Failed to create database: @error', array('@error' => implode(drush_shell_exec_output()))));
    }
  }
}