<?php

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBoot;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Sql\SqlBase;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\AnnotatedCommand\CommandData;

class StatusCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * An overview of the environment - Drush and Drupal.
     *
     * @command core:status
     * @param $filter A field to filter on. @deprecated - use --field option instead.
     * @option project A comma delimited list of projects. Their paths will be added to path-aliases section.
     * @aliases status,st,core-status
     * @table-style compact
     * @list-delimiter :
     * @field-labels
     *   drupal-version: Drupal version
     *   uri: Site URI
     *   db-driver: DB driver
     *   db-hostname: DB hostname
     *   db-port: DB port
     *   db-username: DB username
     *   db-password: DB password
     *   db-name: DB name
     *   db-status: Database
     *   bootstrap: Drupal bootstrap
     *   theme: Default theme
     *   admin-theme: Admin theme
     *   php-bin: PHP binary
     *   php-conf: PHP config
     *   php-os: PHP OS
     *   drush-script: Drush script
     *   drush-version: Drush version
     *   drush-temp: Drush temp
     *   drush-conf: Drush configs
     *   drush-alias-files: Drush aliases
     *   install-profile: Install profile
     *   root: Drupal root
     *   drupal-settings-file: Drupal Settings
     *   site-path: Site path
     *   site: Site path
     *   themes: Themes path
     *   modules: Modules path
     *   files: Files, Public
     *   private: Files, Private
     *   temp: Files, Temp
     *   config-sync: Drupal config
     *   files-path: Files, Public
     *   temp-path: Files, Temp
     *   %paths: Other paths
     * @default-fields drupal-version,uri,db-driver,db-hostname,db-port,db-username,db-name,db-status,bootstrap,theme,admin-theme,php-bin,php-conf,php-os,drush-script,drush-version,drush-temp,drush-conf,drush-alias-files,install-profile,root,site,files,private,temp
     * @pipe-format json
     * @hidden-options project
     * @bootstrap max
     * @topics docs:readme
     *
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     */
    public function status($filter = '', $options = ['project' => self::REQ, 'format' => 'table'])
    {
        $data = $this->getPropertyList($options);

        $result = new PropertyList($data);
        $result->addRendererFunction([$this, 'renderStatusCell']);

        return $result;
    }

    public function getPropertyList($options)
    {
        $boot_manager = Drush::bootstrapManager();
        $boot_object = Drush::bootstrap();
        if (($drupal_root = $boot_manager->getRoot()) && ($boot_object instanceof DrupalBoot)) {
            $status_table['drupal-version'] = $boot_object->getVersion($drupal_root);
            $conf_dir = $boot_object->confPath();
            $settings_file = "$conf_dir/settings.php";
            $status_table['drupal-settings-file'] = file_exists($settings_file) ? $settings_file : '';
            if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_SITE)) {
                $status_table['uri'] = $boot_manager->getUri();
                try {
                    $sql = SqlBase::create($options);
                    $db_spec = $sql->getDbSpec();
                    $status_table['db-driver'] = $db_spec['driver'];
                    if (!empty($db_spec['unix_socket'])) {
                        $status_table['db-socket'] = $db_spec['unix_socket'];
                    } elseif (isset($db_spec['host'])) {
                        $status_table['db-hostname'] = $db_spec['host'];
                    }
                    $status_table['db-username'] = isset($db_spec['username']) ? $db_spec['username'] : null;
                    $status_table['db-password'] = isset($db_spec['password']) ? $db_spec['password'] : null;
                    $status_table['db-name'] = isset($db_spec['database']) ? $db_spec['database'] : null;
                    $status_table['db-port'] = isset($db_spec['port']) ? $db_spec['port'] : null;
                    if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
                        $status_table['install-profile'] = \Drupal::installProfile();
                        if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
                            $status_table['db-status'] = dt('Connected');
                            if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                                $status_table['bootstrap'] = dt('Successful');
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Don't worry be happy.
                }
            }
            if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                $status_table['theme'] = \Drupal::config('system.theme')->get('default');
                $status_table['admin-theme'] = $theme = \Drupal::config('system.theme')->get('admin') ?: 'seven';
            }
        }
        $status_table['php-bin'] = PHP_BINARY;
        $status_table['php-os'] = PHP_OS;
        if ($phpIniFiles = EditCommands::phpIniFiles()) {
            $status_table['php-conf'] = $phpIniFiles;
        }
        $status_table['drush-script'] = DRUSH_COMMAND;
        $status_table['drush-version'] = Drush::getVersion();
        $status_table['drush-temp'] = $this->getConfig()->tmp();
        $status_table['drush-conf'] = Drush::config()->get('runtime.config.paths');
        // List available alias files
        $alias_files = $this->siteAliasManager()->listAllFilePaths();
        sort($alias_files);
        $status_table['drush-alias-files'] = $alias_files;

        $paths = self::pathAliases($options, $boot_manager, $boot_object);
        if (!empty($paths)) {
            foreach ($paths as $target => $one_path) {
                $name = $target;
                if (substr($name, 0, 1) == '%') {
                    $name = substr($name, 1);
                }
                $status_table[$name] = $one_path;
            }
        }

        // Store the paths into the '%paths' index; this will be
        // used by other code, but will not be included in the output
        // of the drush status command.
        $status_table['%paths'] = $paths;

        return $status_table;
    }

    public function renderStatusCell($key, $cellData, FormatterOptions $options)
    {
        if (is_array($cellData)) {
            return implode("\n", $cellData);
        }
        return $cellData;
    }

    /**
     * @hook pre-command core-status
     */
    public function adjustStatusOptions(CommandData $commandData)
    {
        $input = $commandData->input();
        $args = $input->getArguments();
        if (!empty($args['filter'])) {
            $input->setOption('fields', '*' . $args['filter'] . '*');
        }
    }

    /**
     * @param array $options
     * @param BootstrapManager $boot_manager
     * @return array
     */
    public static function pathAliases(array $options, BootstrapManager $boot_manager, $boot)
    {
        $paths = [];
        $site_wide = 'sites/all';
        if ($drupal_root = $boot_manager->getRoot()) {
            $paths['%root'] = $drupal_root;
            if (($boot instanceof DrupalBoot) && ($site_root = $boot->confPath())) {
                $paths['%site'] = $site_root;
                if (is_dir($modules_path = $site_root . '/modules')) {
                    $paths['%modules'] = $modules_path;
                } else {
                    $paths['%modules'] = ltrim($site_wide . '/modules', '/');
                }
                if (is_dir($themes_path = $site_root . '/themes')) {
                    $paths['%themes'] = $themes_path;
                } else {
                    $paths['%themes'] = ltrim($site_wide . '/themes', '/');
                }
                if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
                    try {
                        if (isset($GLOBALS['config_directories'])) {
                            foreach ($GLOBALS['config_directories'] as $label => $unused) {
                                $paths["%config-$label"] = config_get_config_directory($label);
                            }
                        }
                    } catch (\Exception $e) {
                        // Nothing to do.
                    }
                }

                if ($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                    $paths['%files'] = PublicStream::basePath();
                    $paths['%temp'] = file_directory_temp();
                    if ($private_path = PrivateStream::basePath()) {
                        $paths['%private'] = $private_path;
                    }

                    $modules = \Drupal::moduleHandler()->getModuleList();
                    $themes = \Drupal::service('theme_handler')->listInfo();
                    $projects = array_merge($modules, $themes);
                    foreach (explode(',', $options['project']) as $target) {
                        if (array_key_exists($target, $projects)) {
                            $paths['%' . $target] = $drupal_root . '/' . $projects[$target]->getPath();
                        }
                    }
                }
            }
        }

        // Add in all of the global paths from $options['path-aliases']
        // @todo is this used?
        if (isset($options['path-aliases'])) {
            $paths = array_merge($paths, $options['path-aliases']);
        }

        return $paths;
    }
}
