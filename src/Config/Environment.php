<?php
namespace Drush\Config;

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
        $this->originalCwd = $cwd;
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
        // TODO: evaluate these paths.
        // i.e. which is better:
        //   $config->get('drush.base-dir')
        //     - or -
        //   $config->get('drush.base.dir')
        return [
            'env' => [
                'cwd' => $this->cwd(),
                'is-windows' => $this->isWindows(),
            ],
            'drush' => [
                'base-dir' => $this->drushBasePath,
                'vendor-dir' => $this->vendorDir(),
                'docs-dir' => $this->docsPath(),
            ],
        ];
    }

    public function drushBasePath() {
        return $this->drushBasePath;
    }

    public function homeDir()
    {
        return $this->homeDir;
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
    public function vendorDir()
    {
        return $this->vendorDir;
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
    public static function isWindows($os = NULL) {
        return strtoupper(substr($os ?: PHP_OS, 0, 3)) === 'WIN';
    }
}
