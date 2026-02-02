<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBoot;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Drush;
use Drush\Formatters\FormatterTrait;
use Drush\Sql\SqlBase;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: self::NAME,
    description: 'An overview of the environment - Drush and Drupal.',
    aliases: ['status', 'st', 'core-status'],
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'table')]
#[CLI\TableFormat(listDelimiter: ':', tableStyle: 'compact')]
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
    'site' => 'Site path',
    'themes' => 'Themes path',
    'modules' => 'Modules path',
    'files' => 'Files, Public',
    'private' => 'Files, Private',
    'temp' => 'Files, Temp',
    // config-sync is deprecated. Use 'config' instead.
    'config-sync' => 'Drupal config',
    'config' => 'Drupal config',
    '%paths' => 'Other paths'
])]
#[CLI\DefaultTableFields(fields: ['drupal-version', 'uri', 'db-driver', 'db-hostname', 'db-port', 'db-username', 'db-name', 'db-status', 'bootstrap', 'theme', 'admin-theme', 'php-bin', 'php-conf', 'php-os', 'php-version', 'drush-script', 'drush-version', 'drush-temp', 'drush-conf', 'install-profile', 'root', 'site', 'files', 'private', 'temp', 'config'])]
#[CLI\HelpLinks(links: [HelpLinks::Readme])]
class StatusCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    const NAME = 'core:status';

    protected function __construct(
        protected readonly BootstrapManager $bootstrapManager,
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly FormatterManager $formatterManager,
        private readonly DrushConfig $drushConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(name: 'project', mode: InputOption::VALUE_REQUIRED, description: 'A comma delimited list of projects. Their paths will be added to path-aliases section.')
            ->addUsage('drush core-status --field=files')
            ->addUsage('drush core-status --fields=*');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);

        $data = $this->getPropertyList($input->getOptions());
        $result = new PropertyList($data);
        $result->addRendererFunction($this->renderStatusCell(...));

        return $result;
    }

    public function getPropertyList($options): array
    {
        $boot_object = $this->bootstrapManager->bootstrap();
        if ($drupal_root = $this->bootstrapManager->getRoot()) {
            $status_table['drupal-version'] = $boot_object->getVersion($drupal_root);
            $conf_dir = $boot_object->confPath();
            $settings_file = Path::join($conf_dir, 'settings.php');
            $status_table['drupal-settings-file'] = file_exists($settings_file) ? $settings_file : '';
            if ($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::SITE)) {
                $status_table['uri'] = $this->bootstrapManager->getUri();
                try {
                    if ($sql = SqlBase::create($options)) {
                        $db_spec = $sql->getDbSpec();
                        $status_table['db-driver'] = $db_spec['driver'];
                        if (!empty($db_spec['unix_socket'])) {
                            $status_table['db-socket'] = $db_spec['unix_socket'];
                        } elseif (isset($db_spec['host'])) {
                            $status_table['db-hostname'] = $db_spec['host'];
                        }
                        $status_table['db-username'] = $db_spec['username'] ?? null;
                        $status_table['db-password'] = $db_spec['password'] ?? null;
                        $status_table['db-name'] = $db_spec['database'] ?? null;
                        $status_table['db-port'] = $db_spec['port'] ?? null;
                    }
                    if ($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::CONFIGURATION)) {
                        $status_table['install-profile'] = \Drupal::installProfile();
                        if ($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::DATABASE)) {
                            $status_table['db-status'] = 'Connected';
                            if ($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL)) {
                                $status_table['bootstrap'] = 'Successful';
                            }
                        }
                    }
                } catch (\Exception) {
                    // Don't worry be happy.
                }
            }
            if ($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL)) {
                $status_table['theme'] = \Drupal::config('system.theme')->get('default');
                $status_table['admin-theme'] = $theme = \Drupal::config('system.theme')->get('admin') ?: 'seven';
            }
        }
        $status_table['php-bin'] = Path::canonicalize(PHP_BINARY);
        $status_table['php-os'] = PHP_OS;
        $status_table['php-version'] = PHP_VERSION;
        if ($phpIniFiles = EditCommand::phpIniFiles()) {
            $status_table['php-conf'] = array_map(Path::canonicalize(...), $phpIniFiles);
        }
        $status_table['drush-script'] = Path::canonicalize($this->drushConfig->get('runtime.drush-script'));
        $status_table['drush-version'] = Drush::getVersion();
        $status_table['drush-temp'] = Path::canonicalize($this->drushConfig->tmp());
        $status_table['drush-conf'] = array_map(Path::canonicalize(...), $this->drushConfig->configPaths());
        // List available alias files
        $alias_files = $this->siteAliasManager->listAllFilePaths();
        sort($alias_files);
        $status_table['drush-alias-files'] = $alias_files;
        $alias_searchpaths = $this->siteAliasManager->searchLocations();
        $status_table['alias-searchpaths'] = array_map(Path::canonicalize(...), $alias_searchpaths);

        $paths = self::pathAliases($options, $this->bootstrapManager, $boot_object);
        foreach ($paths as $target => $one_path) {
            $name = $target;
            if (str_starts_with($name, '%')) {
                $name = substr($name, 1);
            }
            $status_table[$name] = $one_path;
        }

        // Store the paths into the '%paths' index; this will be
        // used by other code, but will not be included in the default output
        // of the drush status command.
        $status_table['%paths'] = array_map(Path::canonicalize(...), array_filter($paths));

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
                $paths['%themes'] = is_dir($themes_path = $site_root . '/themes') ? $themes_path : ltrim($site_wide . '/themes', '/');
                if ($boot_manager->hasBootstrapped(DrupalBootLevels::CONFIGURATION)) {
                    try {
                        $paths["%config-sync"] = Settings::get('config_sync_directory');
                        $paths["%config"] = Settings::get('config_sync_directory');
                    } catch (\Exception) {
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
