<?php
namespace Drush\Config;

use Composer\Autoload\ClassLoader;

use Drush\Drush;
use Drush\Utils\FsUtils;
use Webmozart\PathUtil\Path;

/**
 * Store information about the environment
 */
class Environment
{
    protected $homeDir;
    protected $originalCwd;
    protected $etcPrefix;
    protected $sharePrefix;
    protected $drushBasePath;
    protected $vendorDir;

    protected $docPrefix;

    protected $loader;
    protected $siteLoader;

    /**
     * Environment constructor
     * @param string $homeDir User home directory.
     * @param string $cwd The current working directory at the time Drush was called.
     * @param string $autoloadFile Path to the autoload.php file.
     */
    public function __construct($homeDir, $cwd, $autoloadFile)
    {
        $this->homeDir = $homeDir;
        $this->originalCwd = Path::canonicalize($cwd);
        $this->etcPrefix = '';
        $this->sharePrefix = '';
        $this->drushBasePath = dirname(dirname(__DIR__));
        $this->vendorDir = FsUtils::realpath(dirname($autoloadFile));
    }

    /**
     * Load the autoloader for the selected Drupal site
     *
     * @param string $root
     * @return ClassLoader
     */
    public function loadSiteAutoloader($root)
    {
        $autloadFilePath = "$root/autoload.php";
        if (!file_exists($autloadFilePath)) {
            return $this->loader;
        }

        if ($this->siteLoader) {
            return $this->siteLoader;
        }

        $this->siteLoader = require $autloadFilePath;
        if ($this->siteLoader === true) {
            // The autoloader was already required. Assume that Drush and Drupal share an autoloader per
            // "Point autoload.php to the proper vendor directory" - https://www.drupal.org/node/2404989
            $this->siteLoader = $this->loader;
        }

        // Ensure that the site's autoloader has highest priority. Usually,
        // the first classloader registered gets the first shot at loading classes.
        // We want Drupal's classloader to be used first when a class is loaded,
        // and have Drush's classloader only be called as a fallback measure.
        $this->siteLoader->unregister();
        $this->siteLoader->register(true);

        return $this->siteLoader;
    }

    /**
     * Return the name of the user running drush.
     *
     * @return string
     */
    protected function getUsername()
    {
        $name = null;
        if (!$name = getenv("username")) { // Windows
            if (!$name = getenv("USER")) {
                // If USER not defined, use posix
                if (function_exists('posix_getpwuid')) {
                    $processUser = posix_getpwuid(posix_geteuid());
                    $name = $processUser['name'];
                }
            }
        }
        return $name;
    }

    protected function getTmp()
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
    public function exportConfigData()
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
     *
     * @return string
     */
    public function drushBasePath()
    {
        return $this->drushBasePath;
    }

    /**
     * Get the site:set alias from the current site:set file path.
     *
     * @return bool|string
     */
    public function getSiteSetAliasName()
    {
        $site_filename = $this->getSiteSetAliasFilePath();
        if (file_exists($site_filename)) {
            $site = file_get_contents($site_filename);
            if ($site) {
                return $site;
            }
        }
        return false;
    }

    /**
     * User's home directory
     *
     * @return string
     */
    public function homeDir()
    {
        return $this->homeDir;
    }

    /**
     * The user's Drush configuration directory, ~/.drush
     *
     * @return string
     */
    public function userConfigPath()
    {
        return $this->homeDir() . '/.drush';
    }

    /**
     * The original working directory
     *
     * @return string
     */
    public function cwd()
    {
        return $this->originalCwd;
    }

    /**
     * Return the path to Drush's vendor directory
     *
     * @return string
     */
    public function vendorPath()
    {
        return $this->vendorDir;
    }

    /**
     * The class loader returned when the autoload.php file is included.
     *
     * @return \Composer\Autoload\ClassLoader
     */
    public function loader()
    {
        return $this->loader;
    }

    /**
     * Set the class loader from the autload.php file, if available.
     *
     * @param \Composer\Autoload\ClassLoader $loader
     */
    public function setLoader(ClassLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Alter our default locations based on the value of environment variables
     *
     * @return $this
     */
    public function applyEnvironment()
    {
        // Copy ETC_PREFIX and SHARE_PREFIX from environment variables if available.
        // This alters where we check for server-wide config and alias files.
        // Used by unit test suite to provide a clean environment.
        $this->setEtcPrefix(getenv('ETC_PREFIX'));
        $this->setSharePrefix(getenv('SHARE_PREFIX'));

        return $this;
    }

    /**
     * Set the directory prefix to locate the directory that Drush will
     * use as /etc (e.g. during the functional tests)
     *
     * @param string $etcPrefix
     * @return $this
     */
    public function setEtcPrefix($etcPrefix)
    {
        if (isset($etcPrefix)) {
            $this->etcPrefix = $etcPrefix;
        }
        return $this;
    }

    /**
     * Set the directory prefix to locate the directory that Drush will
     * use as /user/share (e.g. during the functional tests)
     * @param string $sharePrefix
     * @return $this
     */
    public function setSharePrefix($sharePrefix)
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
     *
     * @return string
     */
    public function docsPath()
    {
        if (!$this->docPrefix) {
            $this->docPrefix = $this->findDocsPath($this->drushBasePath);
        }
        return $this->docPrefix;
    }

    /**
     * Locate the Drush documentation. This is recalculated whenever the
     * share prefix is changed.
     *
     * @param string $drushBasePath
     * @return string
     */
    protected function findDocsPath($drushBasePath)
    {
        $candidates = [
            "$drushBasePath/README.md",
            static::systemPathPrefix($this->sharePrefix, '/usr') . '/share/docs/drush/README.md',
        ];
        return $this->findFromCandidates($candidates);
    }

    /**
     * Check a list of directories and return the first one that exists.
     *
     * @param array $candidates
     * @return string|boolean
     */
    protected function findFromCandidates($candidates)
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
     * @param string $override
     * @param string $defaultPrefix
     * @return string
     */
    protected static function systemPathPrefix($override = '', $defaultPrefix = '')
    {
        if ($override) {
            return $override;
        }
        return static::isWindows() ? getenv('ALLUSERSPROFILE') . '/Drush' : $defaultPrefix;
    }

    /**
     * Return the system configuration path (default: /etc/drush)
     *
     * @return string
     */
    public function systemConfigPath()
    {
        return static::systemPathPrefix($this->etcPrefix, '') . '/etc/drush';
    }

    /**
     * Return the system shared commandfile path (default: /usr/share/drush/commands)
     *
     * @return string
     */
    public function systemCommandFilePath()
    {
        return static::systemPathPrefix($this->sharePrefix, '/usr') . '/share/drush/commands';
    }

    /**
     * Determine whether current OS is a Windows variant.
     *
     * @return boolean
     */
    public static function isWindows($os = null)
    {
        return strtoupper(substr($os ?: PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Verify that we are running PHP through the command line interface.
     *
     * @return boolean
     *   A boolean value that is true when PHP is being run through the command line,
     *   and false if being run through cgi or mod_php.
     */
    public function verifyCLI()
    {
        return (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
    }

    /**
     * Calculate the terminal width used for wrapping table output.
     * Normally this is exported using tput in the drush script.
     * If this is not present we do an additional check using stty here.
     * On Windows in CMD and PowerShell is this exported using mode con.
     *
     * @return integer
     */
    public function calculateColumns()
    {
        if ($columns = getenv('COLUMNS')) {
            return $columns;
        }

        // Trying to export the columns using stty.
        exec('stty size 2>&1', $columns_output, $columns_status);
        if (!$columns_status) {
            $columns = preg_replace('/\d+\s(\d+)/', '$1', $columns_output[0], -1, $columns_count);
        }

        // If stty fails and Drush us running on Windows are we trying with mode con.
        if (($columns_status || !$columns_count) && static::isWindows()) {
            $columns_output = [];
            exec('mode con', $columns_output, $columns_status);
            if (!$columns_status && is_array($columns_output)) {
                $columns = (int)preg_replace('/\D/', '', $columns_output[4], -1, $columns_count);
            }
            // TODO: else { 'Drush could not detect the console window width. Set a Windows Environment Variable of COLUMNS to the desired width.'
        }

        // Failling back to default columns value
        if (empty($columns)) {
            $columns = 80;
        }

        // TODO: should we deal with reserve-margin here, or adjust it later?
        return $columns;
    }

    /**
     * Returns the filename for the file that stores the DRUPAL_SITE variable.
     *
     * @param string $filename_prefix
     *   An arbitrary string to prefix the filename with.
     *
     * @return string|false
     *   Returns the full path to temp file if possible, or FALSE if not.
     */
    protected function getSiteSetAliasFilePath($filename_prefix = 'drush-drupal-site-')
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

        return "{$tmp}/drush-env-{$username}/{$filename_prefix}" . $shell_pid;
    }
}
