<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBoot;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Sql\SqlBase;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Utils\StringUtils;
use Symfony\Component\Filesystem\Path;

final class StatusCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    const STATUS = 'core:status';

    /**
     * An overview of the environment - Drush and Drupal.
     */
    #[CLI\Command(name: self::STATUS, aliases: ['status', 'st', 'core-status'])]
    #[CLI\Option(name: 'project', description: 'A comma delimited list of projects. Their paths will be added to path-aliases section.')]
    #[CLI\Usage(name: 'drush core-status --field=files', description: 'Emit just one field, not all the default fields.')]
    #[CLI\Usage(name: 'drush core-status --fields=*', description: 'Emit all fields, not just the default ones.')]
    #[CLI\Format(listDelimiter: ':', tableStyle: 'compact')]
    #[CLI\FieldLabels(labels: [
        'drupal-version' => 'Drupal version',
        'uri' => 'Site URI',
        'db-driver' => 'DB driver',
        'db-hostname' => 'DB hostname',
        'db-port' => 'DB port',
        'db-username' => 'DB username',
        'db-password' => 'DB password',
        'db-name' => 'DB name',
        'db-status' => 'Database',
        'bootstrap' => 'Drupal bootstrap',
        'theme' => 'Default theme',
        'admin-theme' => 'Admin theme',
        'php-bin' => 'PHP binary',
        'php-conf' => 'PHP config',
        'php-os' => 'PHP OS',
        'php-version' => 'PHP version',
        'drush-script' => 'Drush script',
        'drush-version' => 'Drush version',
        'drush-temp' => 'Drush temp',
        'drush-conf' => 'Drush configs',
        'drush-alias-files' => 'Drush aliases',
        'alias-searchpaths' => 'Alias search paths',
        'install-profile' => 'Install profile',
        'root' => 'Drupal root',
        'drupal-settings-file' => 'Drupal Settings',
        'site-path' => 'Site path',
        'site' => 'Site path',
        'themes' => 'Themes path',
        'modules' => 'Modules path',
        'files' => 'Files, Public',
        'private' => 'Files, Private',
        'temp' => 'Files, Temp',
        'config-sync' => 'Drupal config',
        'files-path' => 'Files, Public',
        'temp-path' => 'Files, Temp',
        '%paths' => 'Other paths'
    ])]
    #[CLI\DefaultTableFields(fields: ['drupal-version', 'uri', 'db-driver', 'db-hostname', 'db-port', 'db-username', 'db-name', 'db-status', 'bootstrap', 'theme', 'admin-theme', 'php-bin', 'php-conf', 'php-os', 'php-version', 'drush-script', 'drush-version', 'drush-temp', 'drush-conf', 'install-profile', 'root', 'site', 'files', 'private', 'temp'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
    #[CLI\Topics(topics: [DocsCommands::README])]
    public function status($options = ['project' => self::REQ, 'format' => 'table']): PropertyList
    {
        $data = $this->getPropertyList($options);

        $result = new PropertyList($data);
        $result->addRendererFunction([$this, 'renderStatusCell']);

        return $result;
    }

    public function getPropertyList($options): array
    {
        $boot_manager = Drush::bootstrapManager();
        $boot_object = Drush::bootstrap();
        if (($drupal_root = $boot_manager->getRoot()) && ($boot_object instanceof DrupalBoot)) {
            $status_table['drupal-version'] = $boot_object->getVersion($drupal_root);
            $conf_dir = $boot_object->confPath();
            $settings_file = Path::join($conf_dir, 'settings.php');
            $status_table['drupal-settings-file'] = file_exists($settings_file) ? $settings_file : '';
            if ($boot_manager->hasBootstrapped(DrupalBootLevels::SITE)) {
                $status_table['uri'] = $boot_manager->getUri();
                try {
                    if ($sql = SqlBase::create($options)) {
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
                    }
                    if ($boot_manager->hasBootstrapped(DrupalBootLevels::CONFIGURATION)) {
                        if (method_exists('Drupal', 'installProfile')) {
                            $status_table['install-profile'] = \Drupal::installProfile();
                        }
                        if ($boot_manager->hasBootstrapped(DrupalBootLevels::DATABASE)) {
                            $status_table['db-status'] = dt('Connected');
                            if ($boot_manager->hasBootstrapped(DrupalBootLevels::FULL)) {
                                $status_table['bootstrap'] = dt('Successful');
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Don't worry be happy.
                }
            }
            if ($boot_manager->hasBootstrapped(DrupalBootLevels::FULL)) {
                $status_table['theme'] = \Drupal::config('system.theme')->get('default');
                $status_table['admin-theme'] = $theme = \Drupal::config('system.theme')->get('admin') ?: 'seven';
            }
        }
        $status_table['php-bin'] = Path::canonicalize(PHP_BINARY);
        $status_table['php-os'] = PHP_OS;
        $status_table['php-version'] = PHP_VERSION;
        if ($phpIniFiles = EditCommands::phpIniFiles()) {
            $status_table['php-conf'] = array_map([Path::class, 'canonicalize'], $phpIniFiles);
        }
        $status_table['drush-script'] = Path::canonicalize($this->getConfig()->get('runtime.drush-script'));
        $status_table['drush-version'] = Drush::getVersion();
        $status_table['drush-temp'] = Path::canonicalize($this->getConfig()->tmp());
        $status_table['drush-conf'] = array_map([Path::class, 'canonicalize'], $this->getConfig()->configPaths());
        // List available alias files
        $alias_files = $this->siteAliasManager()->listAllFilePaths();
        sort($alias_files);
        $status_table['drush-alias-files'] = $alias_files;
        $alias_searchpaths = $this->siteAliasManager()->searchLocations();
        $status_table['alias-searchpaths'] = array_map([Path::class, 'canonicalize'], $alias_searchpaths);

        $paths = self::pathAliases($options, $boot_manager, $boot_object);
        if (!empty($paths)) {
            foreach ($paths as $target => $one_path) {
                $name = $target;
                if (str_starts_with($name, '%')) {
                    $name = substr($name, 1);
                }
                $status_table[$name] = $one_path;
            }
        }

        // Store the paths into the '%paths' index; this will be
        // used by other code, but will not be included in the default output
        // of the drush status command.
        $status_table['%paths'] = array_map([Path::class, 'canonicalize'], array_filter($paths));

        return $status_table;
    }

    public function renderStatusCell($key, $cellData, FormatterOptions $options)
    {
        if ($key == 'drush-version') {
            return Drush::sanitizeVersionString($cellData);
        }
        if (is_array($cellData)) {
            return implode("\n", $cellData);
        }
        return $cellData;
    }

    #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: self::STATUS)]
    public function adjustStatusOptions(CommandData $commandData): void
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
     */
    public static function pathAliases(array $options, BootstrapManager $boot_manager, $boot): array
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
                if ($boot_manager->hasBootstrapped(DrupalBootLevels::CONFIGURATION)) {
                    try {
                        $paths["%config-sync"] = Settings::get('config_sync_directory');
                    } catch (\Exception $e) {
                        // Nothing to do.
                    }
                }

                if ($boot_manager->hasBootstrapped(DrupalBootLevels::FULL)) {
                    $paths['%files'] = PublicStream::basePath();
                    $paths['%temp'] = \Drupal::service('file_system')->getTempDirectory();
                    if ($private_path = PrivateStream::basePath()) {
                        $paths['%private'] = $private_path;
                    }

                    $modules = \Drupal::moduleHandler()->getModuleList();
                    $themes = \Drupal::service('theme_handler')->listInfo();
                    $projects = array_merge($modules, $themes);
                    foreach (StringUtils::csvToArray($options['project']) as $target) {
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
