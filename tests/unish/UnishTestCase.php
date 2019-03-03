<?php

namespace Unish;

use Composer\Autoload\ClassLoader as ComposerClassLoader;
use Consolidation\SiteAlias\AliasRecord;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;
use Consolidation\SiteProcess\ProcessManager;

abstract class UnishTestCase extends TestCase
{
    // Unix exit codes.
    const EXIT_SUCCESS  = 0;

    const EXIT_ERROR = 1;

    const UNISH_EXITCODE_USER_ABORT = 75; // Same as DRUSH_EXITCODE_USER_ABORT

    const INTEGRATION_TEST_ENV = 'default';

    /**
     * @var string[]
     */
    protected static $tickChars = ['/', '-', '\\', '|'];

    /**
     * @var int
     */
    protected static $tickCounter = 0;

    /**
     * @var \Consolidation\SiteProcess\ProcessManager
     */
    protected $processManager;

    /**
     * Process of last executed command.
     *
     * @var \Symfony\Component\Process\Process
     */
    protected $process;

    /**
     * A list of Drupal sites that have been recently installed. They key is the
     * site name and values are details about each site.
     *
     * @var array
     */
    protected static $sites = [];

    /**
     * @deprecated
     */
    protected static $sandbox;

    /**
     * @var string
     */
    protected static $drushExecutable;

    /**
     * @var string
     */
    protected static $gitExecutable = 'git';

    /**
     * @var string
     */
    protected static $dbUrl;

    /**
     * @var string
     */
    protected static $userGroup = '';

    /**
     * @var string
     */
    protected static $backendOutputDelimiter = 'DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END';

    /**
     * @var string
     */
    protected static $drushRootDir = '';

    /**
     * @var string
     */
    protected static $unishSandboxDir = '';

    /**
     * @var int
     */
    protected static $vendorDirDepth = 1;

    /**
     * @var string
     */
    protected static $composerRoot = '';

    /**
     * @var string
     */
    protected static $webRoot = '';

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this
            ->initGitExecutable()
            ->initDrushRootDir()
            ->initDrushExecutable()
            ->initComposerRoot()
            ->initUnishSandboxDir()
            ->initWebRoot()
            ->initUserGroup()
            ->initDbUrl()
            ->initEnvVars();
    }

    /**
     * @return $this
     */
    protected function initComposerRoot()
    {
        $class = new ReflectionClass(ComposerClassLoader::class);
        static::$composerRoot = $class->getFileName();
        for ($i = 0; $i < static::$vendorDirDepth + 2; $i++) {
            static::$composerRoot = dirname(static::$composerRoot);
        }

        return $this;
    }

    protected function initWebRoot()
    {
        $fileName = Path::join(static::$composerRoot, static::getComposerFile());
        $info = file_exists($fileName) ?
            json_decode($this->fileGetContents($fileName), true)
            : [];

        $info += ['extra' => []];
        $info['extra'] += ['installer-paths' => []];

        $coreDir = $this->getComposerInstallerPath(
            $info['extra']['installer-paths'],
            'drupal/core',
            'drupal-core'
        );

        if (!$coreDir) {
            // @todo Lack of $coreDir should be an error.
            $coreDir = 'sut/core';
        }

        static::$webRoot = Path::join(static::getComposerRoot(), Path::getDirectory($coreDir));

        return $this;
    }

    /**
     * @return $this
     */
    protected function initDrushRootDir()
    {
        static::$drushRootDir = Path::canonicalize(__DIR__ . '/../..');

        return $this;
    }

    /**
     * @return $this
     */
    protected function initDrushExecutable()
    {
        static::$drushExecutable = Path::join(static::$drushRootDir, 'drush');

        return $this;
    }

    /**
     * @return $this
     */
    protected function initGitExecutable()
    {
        static::$gitExecutable = 'git';

        return $this;
    }

    /**
     * @return $this
     */
    protected function initUnishSandboxDir()
    {
        static::$unishSandboxDir = Path::join(static::$drushRootDir, 'sandbox');
        static::mkdir(static::$unishSandboxDir);

        return $this;
    }

    /**
     * @return $this
     */
    protected function initUserGroup()
    {
        static::$userGroup = isset($GLOBALS['UNISH_USERGROUP']) ? $GLOBALS['UNISH_USERGROUP'] : null;

        return $this;
    }

    /**
     * @return $this
     */
    protected function initDbUrl()
    {
        static::$dbUrl = getenv('UNISH_DB_URL') ?: (isset($GLOBALS['UNISH_DB_URL']) ? $GLOBALS['UNISH_DB_URL'] : $this->getDefaultDbUrl());

        return $this;
    }

    protected function initEnvVars()
    {
        $cacheDir = Path::join(static::$unishSandboxDir, 'cache');
        $home = Path::join(static::$unishSandboxDir, 'home');

        static::setEnv([
            'CACHE_PREFIX' => $cacheDir,
            'HOME' => $home,
            'HOMEDRIVE' => $home,
            'COMPOSER_HOME' => Path::join($cacheDir, '.composer'),
            'ETC_PREFIX' => static::$unishSandboxDir,
            'SHARE_PREFIX' => static::$unishSandboxDir,
            'TEMP' => Path::join(static::$unishSandboxDir, 'tmp'),
        ]);

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultDbUrl()
    {
        return 'mysql://root:@127.0.0.1';
    }

    /**
     * @return array
     */
    public static function getSites()
    {
        return static::$sites;
    }

    /**
     * @return string[]
     */
    public static function getAliases()
    {
        $aliases = [];

        // Prefix @sut. onto each site.
        foreach (static::$sites as $key => $site) {
            $aliases[$key] = '@sut.' . $key;
        }

        return $aliases;
    }

    /**
     * @param string $site
     *
     * @return string
     */
    public static function getUri($site = 'dev')
    {
        return static::$sites[$site]['uri'];
    }

    /**
     * @return string
     */
    public static function getDrush()
    {
        return static::$drushExecutable;
    }

    /**
     * @return string
     */
    public static function getSandbox()
    {
        return static::$unishSandboxDir;
    }

    /**
     * @return string
     */
    public static function getSut()
    {
        return Path::getDirectory(static::webroot());
    }

    /**
     * @return string
     */
    public static function getComposerRoot()
    {
        return static::$composerRoot;
    }

    /**
     * - Remove sandbox directory.
     * - Empty /modules, /profiles, /themes in SUT.
     */
    public static function cleanDirs()
    {
        $dirty = getenv('UNISH_DIRTY');

        // First step: delete the entire sandbox unless 'UNISH_DIRTY' is set,
        // in which case we will delete only the 'transient' directory.
        $sandbox = static::getSandbox();
        if (!empty($dirty)) {
            $sandbox = Path::join($sandbox, 'transient');
        }

        // The transient files generally should not need to be inspected, but
        // if you need to examine them, use the special value of 'UNISH_DIRTY=VERY'
        // to keep them.
        if (file_exists($sandbox) && ($dirty != 'VERY')) {
            static::recursiveDelete($sandbox);
        }

        // Next step: If 'UNISH_DIRTY' is not set, then delete the portions
        // of our fixtures that we set up dynamically during the tests.
        if (empty($dirty)) {
            $webrootSlashDrush = static::webrootSlashDrush();
            if (file_exists($webrootSlashDrush)) {
                static::recursiveDelete($webrootSlashDrush, true, false, ['Commands', 'sites']);
            }

            foreach (['modules', 'themes', 'profiles'] as $dir) {
                $target = Path::join(static::webroot(), $dir, 'contrib');
                if (file_exists($target)) {
                    static::recursiveDeleteDirContents($target);
                }
            }

            foreach (['sites/dev', 'sites/stage', 'sites/prod'] as $dir) {
                $target = Path::join(static::webroot(), $dir);
                if (file_exists($target)) {
                    static::recursiveDelete($target);
                }
            }
        }
    }

    /**
     * @return string
     */
    public static function getDbUrl()
    {
        return static::$dbUrl;
    }

    /**
     * @return string
     */
    public static function getUserGroup()
    {
        return static::$userGroup;
    }

    /**
     * @return string
     */
    public static function getBackendOutputDelimiter()
    {
        return static::$backendOutputDelimiter;
    }

    /**
     * We used to assure that each class starts with an empty sandbox directory and
     * a clean environment except for the SUT. History: http://drupal.org/node/1103568.
     */
    public static function setUpBeforeClass()
    {
        static::cleanDirs();
        static::createRequiredDirs();
        static::setUpBeforeClassGitConfig();

        parent::setUpBeforeClass();
    }

    protected static function createRequiredDirs()
    {
        // Create all the dirs.
        $sandbox = static::getSandbox();
        $dirs = [
            getenv('HOME') . '/.drush',
            "$sandbox/etc/drush",
            "$sandbox/share/drush/commands",
            "$sandbox/cache",
            getenv('TEMP'),
        ];

        foreach ($dirs as $dir) {
            static::mkdir($dir);
        }
    }

    protected static function setUpBeforeClassGitConfig()
    {
        if (static::isWindows()) {
            // Hack to make git use unix line endings on windows.
            static::gitConfigSet(
                'core.autocrlf',
                'false',
                Path::join(static::getSandbox(), 'home', '.gitconfig')
            );
        }
    }

    protected static function gitConfigSet($key, $value, $file = '')
    {
        $cmdPattern = '%s config';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
        ];

        if ($file) {
            $cmdPattern .= ' --file %s';
            $cmdArgs[] = self::escapeshellarg($file);
        }

        $cmdPattern .= ' %s %s';
        $cmdArgs[] = static::escapeshellarg($key);
        $cmdArgs[] = static::escapeshellarg($value);

        // @todo Do something if $exitCode is not 0.
        exec(vsprintf($cmdPattern, $cmdArgs), $output, $exitCode);
    }

    /**
     * Runs after all tests in a class are run.
     */
    public static function tearDownAfterClass()
    {
        static::cleanDirs();
        static::$sites = [];

        parent::tearDownAfterClass();
    }

    /**
     * Print a log message to the console.
     *
     * @param string $message
     * @param string $type
     *   Supported types are:
     *     - notice
     *     - verbose
     *     - debug
     */
    public function log($message, $type = 'notice')
    {
        $line = "\nLog: $message\n";
        switch ($this->logLevel()) {
            case 'verbose':
                if (in_array($type, ['notice', 'verbose'])) {
                    fwrite(STDERR, $line);
                }
                break;
            case 'debug':
                fwrite(STDERR, $line);
                break;
            default:
                if ($type == 'notice') {
                    fwrite(STDERR, $line);
                }
                break;
        }
    }

    /**
     * @return string
     */
    public function logLevel()
    {
        $argv = $_SERVER['argv'];

        // -d is reserved by `phpunit`
        if (in_array('--debug', $argv) || in_array('-vvv', $argv)) {
            return 'debug';
        }

        if (in_array('--verbose', $argv) || in_array('-v', $argv)) {
            return 'verbose';
        }

        return '';
    }

    public static function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) == 'WIN';
    }

    public static function getTarExecutable()
    {
        return static::isWindows() ? 'bsdtar.exe' : 'tar';
    }

    /**
     * Print out a tick mark.
     *
     * Useful for longer running tests to indicate they're working.
     *
     * @return $this
     */
    public function tick()
    {
        // ANSI support is flaky on Win32, so don't try to do ticks there.
        if (static::isWindows()) {
            return $this;
        }

        print $this->getNextTickChar() . "\033[1D";

        return $this;
    }

    /**
     * @return string
     */
    protected function getNextTickChar()
    {
        $i = static::$tickCounter++ % count(static::$tickChars);

        return static::$tickChars[$i];
    }

    /**
     * Borrowed from Drush.
     * Checks operating system and returns
     * supported bit bucket folder.
     *
     * @return string
     */
    public function bitBucket()
    {
        return static::isWindows() ? 'nul' : '/dev/null';
    }

    /**
     * @param string $arg
     *
     * @return string
     */
    public static function escapeshellarg($arg)
    {
        // Short-circuit escaping for simple params (keep stuff readable).
        if (preg_match('@^[a-zA-Z0-9\.:/_-]*$@', $arg)) {
            return $arg;
        }

        return static::isWindows() ? static::_escapeshellargWindows($arg) : escapeshellarg($arg);
    }

    /**
     * @param string $arg
     *
     * @return string
     */
    public static function _escapeshellargWindows($arg)
    {
        // Double up existing backslashes
        $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);

        // Double up double quotes
        $arg = preg_replace('/"/', '""', $arg);

        // Double up percents.
        // $arg = preg_replace('/%/', '%%', $arg);

        // Add surrounding quotes.
        $arg = '"' . $arg . '"';

        return $arg;
    }

    /**
     * Helper function to generate a random string of arbitrary length.
     *
     * Copied from drush_generate_password(), which is otherwise not available here.
     *
     * @param int $length
     *   Number of characters the generated string should contain.
     *
     * @return string
     *   The generated string.
     */
    public function randomString($length = 10)
    {
        // This variable contains the list of allowable characters for the
        // password. Note that the number 0 and the letter 'O' have been
        // removed to avoid confusion between the two. The same is true
        // of 'I', 1, and 'l'.
        $allowable_characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        // Zero-based count of characters in the allowable list:
        $len = strlen($allowable_characters) - 1;

        // Declare the password as a blank string.
        $pass = '';

        // Loop the number of times specified by $length.
        for ($i = 0; $i < $length; $i++) {
            // Each iteration, pick a random character from the
            // allowable string and append it to the password:
            $pass .= $allowable_characters[mt_rand(0, $len)];
        }

        return $pass;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function mkdir($path)
    {
        if (!is_dir($path)) {
            if (static::mkdir(dirname($path))) {
                if (@mkdir($path)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param string $src
     * @param string $dst
     */
    public static function recursiveCopy($src, $dst)
    {
        $dir = opendir($src);
        static::mkdir($dst);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    static::recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }


    /**
     * Deletes the specified file or directory and everything inside it.
     *
     * Usually respects read-only files and folders. To do a forced delete use
     * drush_delete_tmp_dir() or set the parameter $forced.
     *
     * To avoid permission denied error on Windows, make sure your CWD is not
     * inside the directory being deleted.
     *
     * This is essentially a copy of drush_delete_dir().
     *
     * @todo This sort of duplication isn't very DRY. This is bound to get out of
     *   sync with drush_delete_dir(), as in fact it already has before.
     *
     * @param string $dir
     *   The file or directory to delete.
     * @param bool $force
     *   Whether or not to try everything possible to delete the directory, even if
     *   it's read-only. Defaults to FALSE.
     * @param bool $follow_symlinks
     *   Whether or not to delete symlinked files. Defaults to FALSE--simply
     *   unlinking symbolic links.
     * @param string[] $exclude
     *   Top-level items to retain
     *
     * @return bool
     *   FALSE on failure, TRUE if everything was deleted.
     *
     * @see drush_delete_dir()
     */
    public static function recursiveDelete($dir, $force = true, $follow_symlinks = false, $exclude = [])
    {
        // Do not delete symlinked files, only unlink symbolic links
        if (is_link($dir) && !$follow_symlinks) {
            return unlink($dir);
        }

        // Allow to delete symlinks even if the target doesn't exist.
        if (!is_link($dir) && !file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            if ($force) {
                // Force deletion of items with readonly flag.
                @chmod($dir, 0777);
            }

            return unlink($dir);
        }

        if (static::recursiveDeleteDirContents($dir, $force, $exclude) === false) {
            return false;
        }

        // Don't delete the directory itself if we are retaining some of its contents
        if (!empty($exclude)) {
            return true;
        }

        if ($force) {
            // Force deletion of items with readonly flag.
            @chmod($dir, 0777);
        }

        return rmdir($dir);
    }

    /**
     * Deletes the contents of a directory.
     *
     * This is essentially a copy of drush_delete_dir_contents().
     *
     * @param string $dir
     *   The directory to delete.
     * @param bool $force
     *   Whether or not to try everything possible to delete the contents, even if
     *   they're read-only. Defaults to FALSE.
     * @param string[] $exclude
     *   Top-level items to retain
     *
     * @return bool
     *   FALSE on failure, TRUE if everything was deleted.
     *
     * @see drush_delete_dir_contents()
     */
    public static function recursiveDeleteDirContents($dir, $force = false, $exclude = [])
    {
        $scandir = @scandir($dir);
        if (!is_array($scandir)) {
            return false;
        }

        foreach ($scandir as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (in_array($item, $exclude)) {
                continue;
            }

            if ($force) {
                @chmod($dir, 0777);
            }

            if (!static::recursiveDelete($dir . '/' . $item, $force)) {
                return false;
            }
        }
        return true;
    }

    public static function webroot()
    {
        return static::$webRoot;
    }

    public static function webrootSlashDrush()
    {
        return Path::join(static::webroot(), 'drush');
    }

    public static function directoryCache($subdir = '')
    {
        return getenv('CACHE_PREFIX') . '/' . $subdir;
    }

    /**
     * @param string $env
     *
     * @return string
     */
    public function dbUrl($env)
    {
        $dbUrl = static::getDbUrl();
        $driver = $this->dbDriver($dbUrl);

        return $driver === 'sqlite' ? "sqlite://sites/$env/files/unish.sqlite" : "$dbUrl/" . $this->getDatabaseName($env);
    }

    /**
     * @param string $env
     *
     * @return string
     */
    protected function getDatabaseName($env)
    {
        return "unish_$env";
    }

    /**
     * @param string $dbUrl
     *
     * @return string
     */
    public function dbDriver($dbUrl = '')
    {
        return parse_url($dbUrl ?: static::getDbUrl(), PHP_URL_SCHEME);
    }

    /**
     * Create some fixture sites that only have a 'settings.php' file
     * with a database record.
     *
     * @param array $sites key=site_subdir value=array of extra alias data
     * @param string $aliasGroup Write aliases into a file named group.alias.yml
     */
    public function setUpSettings(array $sites, $aliasGroup = 'fixture')
    {
        foreach ($sites as $subdir => $extra) {
            $this->createSettings($subdir);
        }

        // Create basic site alias data with root and uri
        $siteAliasData = $this->aliasFileData(array_keys($sites));

        // Add in caller-provided site alias data
        $siteAliasData = array_merge_recursive($siteAliasData, $sites);

        $this->writeSiteAliases($siteAliasData, $aliasGroup);
    }

    public function createSettings($subdir)
    {
        $settingsContents = <<<EOT
<?php

\$databases['default']['default'] = array (
  'database' => 'unish_$subdir',
  'username' => 'root',
  'password' => '',
  'prefix' => '',
  'host' => '127.0.0.1',
  'port' => '',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);
\$settings['install_profile'] = 'testing';
EOT;

        $root = $this->webroot();
        $settingsPath = "$root/sites/$subdir/settings.php";
        static::mkdir(dirname($settingsPath));
        $this->filePutContents($settingsPath, $settingsContents);
    }
    /**
     * Prepare (and optionally install) one or more Drupal sites using a single codebase.
     *
     * It is no longer supported to pass alternative versions of Drupal or an alternative install_profile.
     */
    public function setUpDrupal($num_sites = 1, $install = false, $options = [])
    {
        $sites_subdirs_all = ['dev', 'stage', 'prod'];
        $sites_subdirs = array_slice($sites_subdirs_all, 0, $num_sites);
        $root = $this->webroot();

        // Install (if needed).
        foreach ($sites_subdirs as $subdir) {
            $this->installDrupal($subdir, $install, $options);
        }

        // Write an empty sites.php. Needed for multi-site on D8+.
        if (!file_exists($root . '/sites/sites.php')) {
            copy($root . '/sites/example.sites.php', $root . '/sites/sites.php');
        }

        $siteData = $this->aliasFileData($sites_subdirs);
        static::$sites = [];
        foreach ($siteData as $key => $data) {
            static::$sites[$key] = $data;
        }
        return static::$sites;
    }

    public function aliasFileData($sites_subdirs)
    {
        $root = $this->webroot();
        // Stash details about each site.
        $sites = [];
        foreach ($sites_subdirs as $subdir) {
            $sites[$subdir] = [
                'root' => $root,
                'uri' => $subdir,
                'dbUrl' => $this->dbUrl($subdir),
            ];
        }

        return $sites;
    }

    protected function sutAlias($uri = self::INTEGRATION_TEST_ENV)
    {
        return new AliasRecord(['root' => $this->webroot(), 'uri' => $uri], "@sut.$uri");
    }

    /**
     * Write an alias group file and a config file which points to same dir.
     *
     * @param $sites
     */
    public function writeSiteAliases($sites, $aliasGroup = 'sut')
    {
        $target = Path::join(static::webrootSlashDrush(), "sites/$aliasGroup.site.yml");
        $this->mkdir(dirname($target));
        $this->filePutContents($target, Yaml::dump($sites, PHP_INT_MAX, 2));
    }

    /**
     * Install a Drupal site.
     *
     * It is no longer supported to pass alternative versions of Drupal or an alternative install_profile.
     */
    public function installDrupal($env = 'dev', $install = false, $options = [], $refreshSettings = true)
    {
        $root = static::webroot();
        $uri = $env;
        $site = "$root/sites/$uri";

        // If specified, install Drupal as a multi-site.
        if ($install) {
            $this->installSut($uri, $options, $refreshSettings);
        } else {
            $this->mkdir($site);
            touch("$site/settings.php");
        }
    }

    /**
     * @return \Consolidation\SiteProcess\ProcessManager
     */
    protected function processManager()
    {
        if (!$this->processManager) {
            $this->processManager = new ProcessManager();
        }

        return $this->processManager;
    }

    protected function checkInstallSut($uri = self::INTEGRATION_TEST_ENV)
    {
        $sutAlias = $this->sutAlias($uri);
        $options = [
            'root' => $this->webroot(),
            'uri' => $uri
        ];
        // TODO: Maybe there is a faster command to use for this check
        $process = $this->processManager()->siteProcess($sutAlias, [static::getDrush(), 'pm:list'], $options);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->installSut($uri);
        }
    }

    protected function installSut($uri = self::INTEGRATION_TEST_ENV, $optionsFromTest = [], $refreshSettings = true)
    {
        $root = $this->webroot();
        $siteDir = "$root/sites/$uri";
        @mkdir($siteDir);
        chmod("$siteDir", 0777);
        @chmod("$siteDir/settings.php", 0777);
        if ($refreshSettings) {
            copy("$root/sites/default/default.settings.php", "$siteDir/settings.php");
        }
        $sutAlias = $this->sutAlias($uri);
        $options = $optionsFromTest + [
            'root' => $this->webroot(),
            'uri' => $uri,
            'db-url' => $this->dbUrl($uri),
            'sites-subdir' => $uri,
            'yes' => true,
            'quiet' => true,
        ];
        if ($level = $this->logLevel()) {
            $options[$level] = true;
        }
        $process = $this->processManager()->siteProcess($sutAlias, [static::getDrush(), 'site:install', 'testing', 'install_configure_form.enable_update_status_emails=NULL'], $options);
        // Set long timeout because Xdebug slows everything.
        $process->setTimeout(0);
        $this->process = $process;
        $process->run();
        $this->assertTrue($process->isSuccessful(), $this->buildProcessMessage());

        // Give us our write perms back.
        chmod($this->webroot() . "/sites/$uri", 0777);
    }

    /**
     * The sitewide directory for Drupal extensions.
     */
    public function drupalSitewideDirectory()
    {
        return '/sites/all';
    }

    /**
     * Write the provided string to a temporary file that will be
     * automatically deleted one exit.
     */
    protected function writeToTmpFile($contents)
    {
        $transient = Path::join($this->getSandbox(), 'transient');
        static::mkdir($transient);
        $path = tempnam($transient, "unishtmp");
        $this->filePutContents($path, $contents);

        return $path;
    }

    /**
     * Set environment variables that should be passed to child processes.
     *
     * @param array $vars
     *   The variables to set.
     */
    public static function setEnv(array $vars)
    {
        foreach ($vars as $k => $v) {
            putenv($k . '=' . $v);
            // Value must be a string. See \Symfony\Component\Process\Process::getDefaultEnv.
            $_SERVER[$k]= (string) $v;
        }
    }

    /**
     * Borrowed from \Symfony\Component\Process\Exception\ProcessTimedOutException
     *
     * @return string
     */
    public function buildProcessMessage()
    {
        $error = sprintf(
            "%s\n\nExit Code: %s(%s)\n\nWorking directory: %s",
            $this->process->getCommandLine(),
            $this->process->getExitCode(),
            $this->process->getExitCodeText(),
            $this->process->getWorkingDirectory()
        );

        if (!$this->process->isOutputDisabled()) {
            $error .= sprintf(
                "\n\nOutput:\n================\n%s\n\nError Output:\n================\n%s",
                $this->process->getOutput(),
                $this->process->getErrorOutput()
            );
        }

        return $error;
    }

    protected function fileGetContents($fileName)
    {
        $content = file_get_contents($fileName);
        if ($content === false) {
            throw new RuntimeException("Could not read file: '$fileName'");
        }

        return $content;
    }

    protected function filePutContents($fileName, $content)
    {
        $result = file_put_contents($fileName, $content);
        if ($result === false) {
            throw new RuntimeException("Could not write file: '$fileName'");
        }

        return $this;
    }

    /**
     * @param array $installerPaths
     * @param string $name
     * @param string $type
     *
     * @return  string
     */
    protected function getComposerInstallerPath($installerPaths, $name, $type)
    {
        foreach ($installerPaths as $dir => $conditions) {
            if (in_array($name, $conditions) || in_array("type:$type", $conditions)) {
                list($vendor, $project) = explode('/', $name) + [1 => ''];

                return strtr(
                    $dir,
                    [
                        '{$vendor}' => $vendor,
                        '{$name}' => $project,
                    ]
                );
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public static function getComposerFile()
    {
        return trim(getenv('COMPOSER')) ?: './composer.json';
    }
}
