<?php
namespace Drush\Config;

use Composer\Autoload\ClassLoader;

use Webmozart\PathUtil\Path;

class Environment
{
    protected $homeDir;
    protected $originalCwd;
    protected $etcPrefix;
    protected $sharePrefix;
    protected $drushBasePath;
    protected $vendorDir;

    protected $docPrefix;

    public function __construct($homeDir, $cwd, $autoloadFile)
    {
        $this->homeDir = $homeDir;
        $this->originalCwd = Path::canonicalize($cwd);
        $this->etcPrefix = '';
        $this->sharePrefix = '';
        $this->drushBasePath = dirname(dirname(__DIR__));
        $this->vendorDir = dirname($autoloadFile);
    }

    /**
     * Convert the environment object into an exported configuration
     * array. This will be fed though the EnvironmentConfigLoader to
     * be added into the ConfigProcessor, where it will become accessible
     * via the configuration object.
     *
     * So, this seems like a good idea becuase we already have ConfigAwareInterface
     * et. al. that makes the config object easily available via dependency
     * injection. Instead of this, we could also add the Environment object
     * to the DI container and make an EnvironmentAwareInterface & etc.
     *
     * Not convinced that is better, but this mapping will grow.
     */
    public function exportConfigData()
    {
        // TODO: decide how to organize / name this heirarchy.
        // i.e. which is better:
        //   $config->get('drush.base-dir')
        //     - or -
        //   $config->get('drush.base.dir')
        return [
            'env' => [
                'cwd' => $this->cwd(),
                'home' => $this->homeDir(),
                'is-windows' => $this->isWindows(),
            ],
            'drush' => [
                'base-dir' => $this->drushBasePath,
                'vendor-dir' => $this->vendorPath(),
                'docs-dir' => $this->docsPath(),
                'user-dir' => $this->userConfigPath(),
                'system-dir' => $this->systemConfigPath(),
                'system-command-dir' => $this->systemCommandFilePath(),
            ],
        ];
    }

    public function drushBasePath()
    {
        return $this->drushBasePath;
    }

    public function homeDir()
    {
        return $this->homeDir;
    }

    public function userConfigPath()
    {
        return $this->homeDir() . '/.drush';
    }

    /**
     * Return the original working directory
     */
    public function cwd()
    {
        return $this->originalCwd;
    }

    /**
     * Return the path to Drush's vendor directory
     */
    public function vendorPath()
    {
        return $this->vendorDir;
    }

    public function loader()
    {
        return $this->loader;
    }

    /**
     * Set the class loader from the autload.php file, if available.
     */
    public function setLoader(ClassLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Alter our default locations based on the value of environment variables
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

    public function setEtcPrefix($etcPrefix)
    {
        if (isset($etcPrefix)) {
            $this->etcPrefix = $etcPrefix;
        }
        return $this;
    }

    public function setSharePrefix($sharePrefix)
    {
        if (isset($sharePrefix)) {
            $this->sharePrefix = $sharePrefix;
            $this->docPrefix = null;
        }
        return $this;
    }

    public function docsPath()
    {
        if (!$this->docPrefix) {
            $this->docPrefix = $this->findDocsPath($this->drushBasePath);
        }
        return $this->docPrefix;
    }

    protected function findDocsPath($drushBasePath)
    {
        $candidates = [
            "$drushBasePath/README.md",
            static::systemPathPrefix($this->sharePrefix, '/usr') . '/share/docs/drush/README.md',
        ];
        return $this->findFromCandidates($candidates);
    }

    protected function findFromCandidates($candidates)
    {
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return dirname($candidate);
            }
        }
        return false;
    }

    protected static function systemPathPrefix($override = '', $defaultPrefix = '')
    {
        if ($override) {
            return $override;
        }
        return static::isWindows() ? getenv('ALLUSERSPROFILE') . '/Drush' : $defaultPrefix;
    }

    public function systemConfigPath()
    {
        return static::systemPathPrefix($this->etcPrefix, '') . '/etc/drush';
    }

    public function systemCommandFilePath()
    {
        return static::systemPathPrefix($this->sharePrefix, '/usr') . '/share/drush/commands';
    }

    /**
     * Determine whether current OS is a Windows variant.
     */
    public static function isWindows($os = null)
    {
        return strtoupper(substr($os ?: PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Verify that we are running PHP through the command line interface.
     *
     * @return
     *   A boolean value that is true when PHP is being run through the command line,
     *   and false if being run through cgi or mod_php.
     */
    function verifyCLI() {
      return (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
    }

}
