<?php

namespace Unish;

use Composer\Semver\Comparator;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteProcess\SiteProcess;
use PHPUnit\Framework\TestCase;
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

    protected $processManager;

    /**
     * Process of last executed command.
     *
     * @var Process
     */
    protected $process;

    /**
     * A list of Drupal sites that have been recently installed. They key is the
     * site name and values are details about each site.
     *
     * @var array
     */
    private static $sites = [];

    private static $sandbox;

    private static $drush;

    private static $db_url;

    private static $usergroup = null;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // We read from env then globals then default to mysql.
        self::$db_url = getenv('UNISH_DB_URL') ?: (isset($GLOBALS['UNISH_DB_URL']) ? $GLOBALS['UNISH_DB_URL'] : 'mysql://root:@127.0.0.1');

        // require_once __DIR__ . '/unish.inc';
        // list($unish_tmp, $unish_sandbox, $unish_drush_dir) = \unishGetPaths();
        $unish_sandbox = Path::join(dirname(dirname(__DIR__)), 'sandbox');
        self::mkdir($unish_sandbox);
        $unish_cache = Path::join($unish_sandbox, 'cache');

        self::$drush = Path::join(self::getComposerRoot(), 'drush');

        self::$sandbox = $unish_sandbox;
        self::$usergroup = isset($GLOBALS['UNISH_USERGROUP']) ? $GLOBALS['UNISH_USERGROUP'] : null;

        self::setEnv(['CACHE_PREFIX' => $unish_cache]);
        $home = $unish_sandbox . '/home';
        self::setEnv(['HOME' => $home]);
        self::setEnv(['HOMEDRIVE' => $home]);
        $composer_home = $unish_cache . '/.composer';
        self::setEnv(['COMPOSER_HOME' => $composer_home]);
        self::setEnv(['ETC_PREFIX' => $unish_sandbox]);
        self::setEnv(['SHARE_PREFIX' => $unish_sandbox]);
        self::setEnv(['TEMP' => Path::join($unish_sandbox, 'tmp')]);
    }

    /**
     * @return array
     */
    public static function getSites()
    {
        return self::$sites;
    }

    /**
     * @return array
     */
    public static function getAliases()
    {
        // Prefix @sut. onto each site.
        foreach (self::$sites as $key => $site) {
            $aliases[$key] = '@sut.' . $key;
        }
        return $aliases;
    }

    public static function getUri($site = 'dev')
    {
        return self::$sites[$site]['uri'];
    }

    /**
     * @return string
     */
    public static function getDrush()
    {
        return self::$drush;
    }

    /**
     * @return string
     */
    public static function getSandbox()
    {
        return self::$sandbox;
    }

    /**
     * @return string
     */
    public static function getSut()
    {
        return self::getComposerRoot();
    }

    public static function getComposerRoot()
    {
        return Path::canonicalize(dirname(dirname(__DIR__)));
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
        $sandbox = self::getSandbox();
        if (!empty($dirty)) {
            $sandbox = Path::join($sandbox, 'transient');
        }
        // The transient files generally should not need to be inspected, but
        // if you need to examine them, use the special value of 'UNISH_DIRTY=VERY'
        // to keep them.
        if (file_exists($sandbox) && ($dirty != 'VERY')) {
            self::recursiveDelete($sandbox);
        }

        // Next step: If 'UNISH_DIRTY' is not set, then delete the portions
        // of our fixtures that we set up dynamically during the tests.
        if (empty($dirty)) {
            $webrootSlashDrush = self::webrootSlashDrush();
            if (file_exists($webrootSlashDrush)) {
                self::recursiveDelete($webrootSlashDrush, true, false, ['Commands', 'sites']);
            }
            foreach (['modules', 'themes', 'profiles'] as $dir) {
                $target = Path::join(self::webroot(), $dir, 'contrib');
                if (file_exists($target)) {
                    self::recursiveDeleteDirContents($target);
                }
            }
            foreach (['sites/dev', 'sites/stage', 'sites/prod'] as $dir) {
                $target = Path::join(self::webroot(), $dir);
                if (file_exists($target)) {
                    self::recursiveDelete($target);
                }
            }
        }
    }

    /**
     * @return string
     */
    public static function getDbUrl()
    {
        return self::$db_url;
    }

    /**
     * @return string
     */
    public static function getUserGroup()
    {
        return self::$usergroup;
    }

    /**
     * We used to assure that each class starts with an empty sandbox directory and
     * a clean environment except for the SUT. History: http://drupal.org/node/1103568.
     */
    public static function setUpBeforeClass()
    {
        self::cleanDirs();

        // Create all the dirs.
        $sandbox = self::getSandbox();
        $dirs = [getenv('HOME') . '/.drush', $sandbox . '/etc/drush', $sandbox . '/share/drush/commands', "$sandbox/cache", getenv('TEMP')];
        foreach ($dirs as $dir) {
            self::mkdir($dir);
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Hack to make git use unix line endings on windows
            exec("git config --file $sandbox\\home\\.gitconfig core.autocrlf false", $output, $return);
        }
        parent::setUpBeforeClass();
    }

    /**
     * Runs after all tests in a class are run.
     */
    public static function tearDownAfterClass()
    {
        self::cleanDirs();
        self::$sites = [];
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

    public function logLevel()
    {
        $argv = $_SERVER['argv'];
        // -d is reserved by `phpunit`
        if (in_array('--debug', $argv) || in_array('-vvv', $argv)) {
            return 'debug';
        } elseif (in_array('--verbose', $argv) || in_array('-v', $argv)) {
            return 'verbose';
        }
    }

    public static function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) == "WIN";
    }

    /**
     * Print out a tick mark.
     *
     * Useful for longer running tests to indicate they're working.
     */
    public function tick()
    {
        static $chars = ['/', '-', '\\', '|'];
        static $counter = 0;
        // ANSI support is flaky on Win32, so don't try to do ticks there.
        if (!$this->isWindows()) {
            print $chars[($counter++ % 4)] . "\033[1D";
        }
    }

    /**
     * Borrowed from Drush.
     * Checks operating system and returns
     * supported bit bucket folder.
     */
    public function bitBucket()
    {
        if (!$this->isWindows()) {
            return '/dev/null';
        } else {
            return 'nul';
        }
    }

    public static function escapeshellarg($arg)
    {
        // Short-circuit escaping for simple params (keep stuff readable)
        if (preg_match('|^[a-zA-Z0-9.:/_-]*$|', $arg)) {
            return $arg;
        } elseif (self::isWindows()) {
            return self::_escapeshellargWindows($arg);
        } else {
            return escapeshellarg($arg);
        }
    }

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
     * @param $length
     *   Number of characters the generated string should contain.
     * @return
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

    public static function mkdir($path)
    {
        if (!is_dir($path)) {
            if (self::mkdir(dirname($path))) {
                if (@mkdir($path)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    public static function recursiveCopy($src, $dst)
    {
        $dir = opendir($src);
        self::mkdir($dst);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    self::recursiveCopy($src . '/' . $file, $dst . '/' . $file);
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
        if (self::recursiveDeleteDirContents($dir, $force, $exclude) === false) {
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
            if (!self::recursiveDelete($dir . '/' . $item, $force)) {
                return false;
            }
        }
        return true;
    }

    public static function webroot()
    {
        return Path::join(self::getSut(), 'sut');
    }

    public static function webrootSlashDrush()
    {
        return Path::join(self::webroot(), 'drush');
    }

    public static function directoryCache($subdir = '')
    {
        return getenv('CACHE_PREFIX') . '/' . $subdir;
    }

    /**
     * @param $env
     * @return string
     */
    public function dbUrl($env)
    {
        return substr(self::getDbUrl(), 0, 6) == 'sqlite'  ?  "sqlite://sites/$env/files/unish.sqlite" : self::getDbUrl() . '/unish_' . $env;
    }

    public function dbDriver($db_url = null)
    {
        return parse_url($db_url ?: self::getDbUrl(), PHP_URL_SCHEME);
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
        $siteAliasData = $this->aliasFileData(array_keys($sites), $aliasGroup);
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
        self::mkdir(dirname($settingsPath));
        file_put_contents($settingsPath, $settingsContents);
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
        self::$sites = [];
        foreach ($siteData as $key => $data) {
            self::$sites[$key] = $data;
        }
        return self::$sites;
    }

    /**
     * Test if current Drupal is >= a target version.
     *
     * @param string $version2
     * @return bool
     */
    public function isDrupalGreaterThanOrEqualTo($version2)
    {
        return Comparator::greaterThanOrEqualTo(\Drupal::VERSION, $version2);
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
        return new SiteAlias(['root' => $this->webroot(), 'uri' => $uri], "@sut.$uri");
    }

    /**
     * Write an alias group file and a config file which points to same dir.
     *
     * @param $sites
     */
    public function writeSiteAliases($sites, $aliasGroup = 'sut')
    {
        $target = Path::join(self::webrootSlashDrush(), "sites/$aliasGroup.site.yml");
        $this->mkdir(dirname($target));
        file_put_contents($target, Yaml::dump($sites, PHP_INT_MAX, 2));
    }

    /**
     * Install a Drupal site.
     *
     * It is no longer supported to pass alternative versions of Drupal or an alternative install_profile.
     */
    public function installDrupal($env = 'dev', $install = false, $options = [], $refreshSettings = true)
    {
        $root = $this->webroot();
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
        $process = $this->processManager()->siteProcess($sutAlias, [self::getDrush(), 'pm:list'], $options);
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
            // quiet suppresses error reporting as well.
            // 'quiet' => true,
        ];
        if ($level = $this->logLevel()) {
            $options[$level] = true;
        }
        $process = $this->processManager()->siteProcess($sutAlias, [self::getDrush(), 'site:install', 'testing', 'install_configure_form.enable_update_status_emails=NULL'], $options);
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
        self::mkdir($transient);
        $path = tempnam($transient, "unishtmp");
        file_put_contents($path, $contents);
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
}
