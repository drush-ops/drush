<?php

declare(strict_types=1);

namespace Drush\Config;

use Composer\Autoload\ClassLoader;
use Drush\Drush;
use Drush\Utils\FsUtils;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Path;

/**
 * Store information about the environment
 */
class Environment
{
    protected string $homeDir;
    protected string $originalCwd;
    protected string $etcPrefix;
    protected string $sharePrefix;
    protected string $drushBasePath;
    protected string $vendorDir;

    protected ?string $docPrefix;
    protected string $configFileVariant;

    protected ClassLoader $loader;
    protected ?Classloader $siteLoader = null;

    /**
     * Environment constructor
     * @param string $homeDir User home directory.
     * @param string $cwd The current working directory at the time Drush was called.
     * @param string $autoloadFile Path to the autoload.php file.
     */
    public function __construct(string $homeDir, string $cwd, string $autoloadFile)
    {
        $this->homeDir = $homeDir;
        $this->originalCwd = Path::canonicalize(FsUtils::realpath($cwd));
        $this->etcPrefix = '';
        $this->sharePrefix = '';
        $this->drushBasePath = Path::canonicalize(dirname(dirname(__DIR__)));
        $this->vendorDir = FsUtils::realpath(dirname($autoloadFile));
    }

    /**
     * Return the name of the user running drush.
     */
    protected function getUsername(): string
    {
        if (!$name = getenv("username")) { // Windows
            if (!$name = getenv("USER")) {
                // If USER not defined, use posix
                if (function_exists('posix_getpwuid')) {
                    if ($processUser = posix_getpwuid(posix_geteuid())) {
                        $name = $processUser['name'];
                    }
                }
            }
        }
        return $name ?: '';
    }

    protected function getTmp(): string
    {
        $directories = [];

        // Get user specific and operating system temp folders from system environment variables.
        // See http://www.microsoft.com/resources/documentation/windows/xp/all/proddocs/en-us/ntcmds_shelloverview.mspx?mfr=true
        $tempdir = getenv('TEMP');
        if (!empty($tempdir)) {
            $directories[] = $tempdir;
        }
        $tmpdir = getenv('TMP');
        if (!empty($tmpdir)) {
            $directories[] = $tmpdir;
        }
        // Operating system specific dirs.
        if (self::isWindows()) {
            $windir = getenv('WINDIR');
            if (isset($windir)) {
                // WINDIR itself is not writable, but it always contains a /Temp dir,
                // which is the system-wide temporary directory on older versions. Newer
                // versions only allow system processes to use it.
                $directories[] = Path::join($windir, 'Temp');
            }
        } else {
            $directories[] = Path::canonicalize('/tmp');
        }
        $directories[] = Path::canonicalize(sys_get_temp_dir());

        foreach ($directories as $directory) {
            if (is_dir($directory) && is_writable($directory)) {
                $temporary_directory = $directory;
                break;
            }
        }

        if (empty($temporary_directory)) {
            // If no directory has been found, create one in cwd.
            $temporary_directory = Path::join(Drush::config()->cwd(), 'tmp');
            drush_mkdir($temporary_directory, true);
            if (!is_dir($temporary_directory)) {
                throw new \Exception(dt("Unable to create a temporary directory."));
            }
            // Function not available yet - this is not likely to get reached anyway.
            // drush_register_file_for_deletion($temporary_directory);
        }
        return $temporary_directory;
    }

    /**
     * Convert the environment object into an exported configuration
     * array.
     *
     * @see PreflightArgs::applyToConfig(), which also exports information to config.
     *
     * @return array Nested associative array that is overlayed on configuration.
     */
    public function exportConfigData(): array
    {
        return [
            // Information about the environment presented to Drush
            'env' => [
                'cwd' => $this->cwd(),
                'home' => $this->homeDir(),
                'user' => $this->getUsername(),
                'is-windows' => $this->isWindows(),
                'tmp' => $this->getTmp(),
            ],
            // These values are available as global options, and
            // will be passed in to the FormatterOptions et. al.
            'options' => [
                'width' => $this->calculateColumns(),
            ],
            // Information about the directories where Drush found assets, etc.
            'drush' => [
                'base-dir' => $this->drushBasePath,
                'vendor-dir' => $this->vendorPath(),
                'docs-dir' => $this->docsPath(),
                'user-dir' => $this->userConfigPath(),
                'system-dir' => $this->systemConfigPath(),
                'system-command-dir' => $this->systemCommandFilePath(),
            ],
            'runtime' => [
                'site-file-previous' => $this->getSiteSetAliasFilePath('drush-drupal-prev-site-'),
                'site-file-current' => $this->getSiteSetAliasFilePath(),
            ],
        ];
    }

    /**
     * The base directory of the Drush application itself
     * (where composer.json et.al. are found)
     */
    public function drushBasePath(): string
    {
        return $this->drushBasePath;
    }

    /**
     * Get the site:set alias from the current site:set file path.
     */
    public function getSiteSetAliasName(): bool|string
    {
        $site_filename = $this->getSiteSetAliasFilePath();
        if ($site_filename && file_exists($site_filename)) {
            $site = file_get_contents($site_filename);
            if ($site) {
                return $site;
            }
        }
        return false;
    }

    /**
     * User's home directory
     */
    public function homeDir(): string
    {
        return $this->homeDir;
    }

    /**
     * The user's Drush configuration directory, ~/.drush
     */
    public function userConfigPath(): string
    {
        return $this->homeDir() . '/.drush';
    }

    public function setConfigFileVariant($variant): void
    {
        $this->configFileVariant = $variant;
    }

    /**
     * Get the config file variant -- defined to be
     * the Drush major version number. This is for
     * loading drush.yml and drush10.yml, etc.
     */
    public function getConfigFileVariant()
    {
        return $this->configFileVariant;
    }

    /**
     * The original working directory
     */
    public function cwd(): string
    {
        return $this->originalCwd;
    }

    /**
     * Return the path to Drush's vendor directory
     */
    public function vendorPath(): string
    {
        return $this->vendorDir;
    }

    /**
     * The class loader returned when the autoload.php file is included.
     */
    public function loader(): ?ClassLoader
    {
        return $this->loader;
    }

    /**
     * Set the class loader from the autload.php file, if available.
     *
     * @param ClassLoader $loader
     */
    public function setLoader(ClassLoader $loader): void
    {
        $this->loader = $loader;
    }

    /**
     * Alter our default locations based on the value of environment variables.
     */
    public function applyEnvironment(): self
    {
        // Copy ETC_PREFIX and SHARE_PREFIX from environment variables if available.
        // This alters where we check for server-wide config and alias files.
        // Used by unit test suite to provide a clean environment.
        $this->setEtcPrefix(getenv('ETC_PREFIX'));
        $this->setSharePrefix((string)getenv('SHARE_PREFIX'));

        return $this;
    }

    /**
     * Set the directory prefix to locate the directory that Drush will
     * use as /etc (e.g. during the functional tests).
     */
    public function setEtcPrefix(mixed $etcPrefix): self
    {
        if (!empty($etcPrefix)) {
            $this->etcPrefix = $etcPrefix;
        }
        return $this;
    }

    /**
     * Set the directory prefix to locate the directory that Drush will
     * use as /user/share (e.g. during the functional tests).
     */
    public function setSharePrefix(string $sharePrefix): self
    {
        if (isset($sharePrefix)) {
            $this->sharePrefix = $sharePrefix;
            $this->docPrefix = null;
        }
        return $this;
    }

    /**
     * Return the directory where Drush's documentation is stored. Usually
     * this is within the Drush application, but some Drush RPM distributions
     * & c. for Linux platforms slice-and-dice the contents and put the docs
     * elsewhere.
     */
    public function docsPath(): ?string
    {
        if (!$this->docPrefix) {
            $this->docPrefix = $this->findDocsPath($this->drushBasePath);
        }
        return $this->docPrefix;
    }

    /**
     * Locate the Drush documentation. This is recalculated whenever the
     * share prefix is changed.
     */
    protected function findDocsPath(string $drushBasePath): string|bool
    {
        $candidates = [
            "$drushBasePath/README.md",
            static::systemPathPrefix($this->sharePrefix, '/usr') . '/share/docs/drush/README.md',
        ];
        return $this->findFromCandidates($candidates);
    }

    /**
     * Check a list of directories and return the first one that exists.
     */
    protected function findFromCandidates(array $candidates): bool|string
    {
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return dirname($candidate);
            }
        }
        return false;
    }

    /**
     * Return the appropriate system path prefix, unless an override is provided.
     */
    protected static function systemPathPrefix(string $override = '', string $defaultPrefix = ''): string
    {
        if ($override) {
            return $override;
        }
        return static::isWindows() ? Path::join(getenv('ALLUSERSPROFILE'), 'Drush') : $defaultPrefix;
    }

    /**
     * Return the system configuration path (default: /etc/drush)
     */
    public function systemConfigPath(): string
    {
        return static::systemPathPrefix($this->etcPrefix) . '/etc/drush';
    }

    /**
     * Return the system shared commandfile path (default: /usr/share/drush/commands)
     */
    public function systemCommandFilePath(): string
    {
        return static::systemPathPrefix($this->sharePrefix, '/usr') . '/share/drush/commands';
    }

    /**
     * Determine whether current OS is a Windows variant.
     */
    public static function isWindows($os = null): bool
    {
        return strtoupper(substr($os ?: PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Verify that we are running PHP through the command line interface.
     *
     *   A boolean value that is true when PHP is being run through the command line,
     *   and false if being run through cgi or mod_php.
     */
    public function verifyCLI(): bool
    {
        return (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
    }

    /**
     * Get terminal width.
     */
    public function calculateColumns(): int
    {
        return (new Terminal())->getWidth();
    }

    /**
     * Returns the filename for the file that stores the DRUPAL_SITE variable.
     *
     * @param $filename_prefix
     *   An arbitrary string to prefix the filename with.
     *
     * @return string|false
     *   Returns the full path to temp file if possible, or FALSE if not.
     */
    protected function getSiteSetAliasFilePath(string $filename_prefix = 'drush-drupal-site-'): string|false
    {
        $shell_pid = getenv('DRUSH_SHELL_PID');
        if (!$shell_pid && function_exists('posix_getppid')) {
            $shell_pid = posix_getppid();
        }
        if (!$shell_pid) {
            return false;
        }

        // The env variables below must match the variables in example.prompt.sh
        $tmp = getenv('TMPDIR') ? getenv('TMPDIR') : '/tmp';
        $username = $this->getUsername();

        return "$tmp/drush-env-{$username}/{$filename_prefix}" . $shell_pid;
    }
}
