<?php

namespace Unish;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

abstract class UnishTestCase extends \PHPUnit_Framework_TestCase {

  /**
   * A list of Drupal sites that have been recently installed. They key is the
   * site name and values are details about each site.
   *
   * @var array
   */
  private static $sites = array();

  private static $sandbox;

  private static $drush;

  private static $tmp;

  private static $db_url;

  private static $usergroup = NULL;

  private static $backendOutputDelimiter = 'DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END';

  /**
   * @return array
   */
  public static function getSites() {
    return self::$sites;
  }

  public static function getUri($site = 'unish.dev') {
    return self::$sites[$site]['uri'];
  }

  /**
   * @return string
   */
  public static function getDrush() {
    return self::$drush;
  }

  /**
   * @return string
   */
  public static function getTmp() {
    return self::$tmp;
  }

  /**
   * @return string
   */
  public static function getSandbox() {
    return self::$sandbox;
  }

  /**
   * @return string
   */
  public static function getSut() {
    return Path::join(self::getTmp(), 'drush-sut');
  }

  /**
   * - Remove sandbox directory.
   * - Empty /modules, /profiles, /themes in SUT.
   */
  public static function cleanDirs() {
    if (empty(getenv('UNISH_DIRTY'))) {
      $sandbox = self::getSandBox();
      if (file_exists($sandbox)) {
        self::recursive_delete($sandbox);
      }
      foreach (['modules', 'themes', 'profiles', 'drush'] as $dir) {
        $target = Path::join(self::getSut(), 'web', $dir, 'contrib');
        if (file_exists($target)) {
          self::recursive_delete_dir_contents($target);
        }
      }
    }
  }

  /**
   * @return string
   */
  public static function getDbUrl() {
    return self::$db_url;
  }

  /**
   * @return string
   */
  public static function getUserGroup() {
    return self::$usergroup;
  }

  /**
   * @return string
   */
  public static function getBackendOutputDelimiter() {
    return self::$backendOutputDelimiter;
  }

  function __construct($name = NULL, array $data = array(), $dataName = '') {
    parent::__construct($name, $data, $dataName);

    // Default drupal major version to run tests over.
    // @todo Remove this.
    if (!defined('UNISH_DRUPAL_MAJOR_VERSION')) {
      define('UNISH_DRUPAL_MAJOR_VERSION', '8');
    }

    // We read from env then globals then default to mysql.
    self::$db_url = getenv('UNISH_DB_URL') ?: (isset($GLOBALS['UNISH_DB_URL']) ? $GLOBALS['UNISH_DB_URL'] : 'mysql://root:@127.0.0.1');

    require_once __DIR__ . '/unish.inc';
    list($unish_tmp, $unish_sandbox, $unish_drush_dir) = \unishGetPaths();
    $unish_cache = Path::join($unish_sandbox, 'cache');

    self::$drush = $unish_drush_dir . '/drush';
    self::$tmp = $unish_tmp;
    self::$sandbox = $unish_sandbox;
    self::$usergroup = isset($GLOBALS['UNISH_USERGROUP']) ? $GLOBALS['UNISH_USERGROUP'] : NULL;

    putenv("CACHE_PREFIX=" . $unish_cache);
    $home = $unish_sandbox . '/home';
    putenv("HOME=$home");
    putenv("HOMEDRIVE=$home");
    $composer_home = $unish_cache . '/.composer';
    putenv("COMPOSER_HOME=$composer_home");

    putenv('ETC_PREFIX=' . $unish_sandbox);
    putenv('SHARE_PREFIX=' . $unish_sandbox);
    putenv('TEMP=' . Path::join($unish_sandbox, 'drush-tmp'));
    putenv('DRUSH_AUTOLOAD_PHP=' . PHPUNIT_COMPOSER_INSTALL);
  }

  /**
   * We used to assure that each class starts with an empty sandbox directory and
   * a clean environment except for the SUT. History: http://drupal.org/node/1103568.
   */
  public static function setUpBeforeClass() {
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
  public static function tearDownAfterClass() {
    self::cleanDirs();

    self::$sites = array();
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
  function log($message, $type = 'notice') {
    $line = "\nLog: $message\n";
    switch ($this->log_level()) {
      case 'verbose':
        if (in_array($type, array('notice', 'verbose'))) fwrite(STDERR, $line);
        break;
      case 'debug':
        fwrite(STDERR, $line);
        break;
      default:
        if ($type == 'notice') fwrite(STDERR, $line);
        break;
    }
  }

  function log_level() {
    // -d is reserved by `phpunit`
    if (in_array('--debug', $_SERVER['argv'])) {
      return 'debug';
    }
    elseif (in_array('--verbose', $_SERVER['argv']) || in_array('-v', $_SERVER['argv'])) {
      return 'verbose';
    }
  }

  public static function is_windows() {
    return strtoupper(substr(PHP_OS, 0, 3)) == "WIN";
  }

  public static function get_tar_executable() {
    return self::is_windows() ? "bsdtar.exe" : "tar";
  }

  /**
   * Print out a tick mark.
   *
   * Useful for longer running tests to indicate they're working.
   */
  function tick() {
    static $chars = array('/', '-', '\\', '|');
    static $counter = 0;
    // ANSI support is flaky on Win32, so don't try to do ticks there.
    if (!$this->is_windows()) {
      print $chars[($counter++ % 4)] . "\033[1D";
    }
  }

  /**
   * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
   * Also converts a cygwin "drive emulation" path (/cygdrive/c/dir1) into a
   * proper drive path, still with Unix slashes (c:/dir1).
   *
   * @copied from Drush's environment.inc
   */
  function convert_path($path) {
    $path = str_replace('\\','/', $path);
    $path = preg_replace('/^\/cygdrive\/([A-Za-z])(.*)$/', '\1:\2', $path);

    return $path;
  }

  /**
   * Borrowed from Drush.
   * Checks operating system and returns
   * supported bit bucket folder.
   */
  function bit_bucket() {
    if (!$this->is_windows()) {
      return '/dev/null';
    }
    else {
      return 'nul';
    }
  }

  public static function escapeshellarg($arg) {
    // Short-circuit escaping for simple params (keep stuff readable)
    if (preg_match('|^[a-zA-Z0-9.:/_-]*$|', $arg)) {
      return $arg;
    }
    elseif (self::is_windows()) {
      return self::_escapeshellarg_windows($arg);
    }
    else {
      return escapeshellarg($arg);
    }
  }

  public static function _escapeshellarg_windows($arg) {
    // Double up existing backslashes
    $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);

    // Double up double quotes
    $arg = preg_replace('/"/', '""', $arg);

    // Double up percents.
    $arg = preg_replace('/%/', '%%', $arg);

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
  public function randomString($length = 10) {
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

  public static function mkdir($path) {
    if (!is_dir($path)) {
      if (self::mkdir(dirname($path))) {
        if (@mkdir($path)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    return TRUE;
  }

  public static function recursive_copy($src, $dst) {
    $dir = opendir($src);
    self::mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
      if (( $file != '.' ) && ( $file != '..' )) {
        if ( is_dir($src . '/' . $file) ) {
          self::recursive_copy($src . '/' . $file,$dst . '/' . $file);
        }
        else {
          copy($src . '/' . $file,$dst . '/' . $file);
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
   *
   * @return bool
   *   FALSE on failure, TRUE if everything was deleted.
   *
   * @see drush_delete_dir()
   */
  public static function recursive_delete($dir, $force = TRUE, $follow_symlinks = FALSE) {
    // Do not delete symlinked files, only unlink symbolic links
    if (is_link($dir) && !$follow_symlinks) {
      return unlink($dir);
    }
    // Allow to delete symlinks even if the target doesn't exist.
    if (!is_link($dir) && !file_exists($dir)) {
      return TRUE;
    }
    if (!is_dir($dir)) {
      if ($force) {
        // Force deletion of items with readonly flag.
        @chmod($dir, 0777);
      }
      return unlink($dir);
    }
    if (self::recursive_delete_dir_contents($dir, $force) === FALSE) {
      return FALSE;
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
   *
   * @return bool
   *   FALSE on failure, TRUE if everything was deleted.
   *
   * @see drush_delete_dir_contents()
   */
  public static function recursive_delete_dir_contents($dir, $force = FALSE) {
    $scandir = @scandir($dir);
    if (!is_array($scandir)) {
      return FALSE;
    }

    foreach ($scandir as $item) {
      if ($item == '.' || $item == '..') {
        continue;
      }
      if ($force) {
        @chmod($dir, 0777);
      }
      if (!self::recursive_delete($dir . '/' . $item, $force)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  function webroot() {
    return Path::join(self::getSut(), 'web');
  }

  function directory_cache($subdir = '') {
    return getenv('CACHE_PREFIX') . '/' . $subdir;
  }

  /**
   * @param $env
   * @return string
   */
  function db_url($env) {
    return substr(self::getDbUrl(), 0, 6) == 'sqlite'  ?  "sqlite://sites/$env/files/unish.sqlite" : self::getDbUrl() . '/unish_' . $env;
  }

  function db_driver($db_url = NULL) {
    return parse_url($db_url ?: self::getDbUrl(), PHP_URL_SCHEME);
  }

  /**
   * Create some fixture sites that only have a 'settings.php' file
   * with a database record.
   *
   * @param array $sites key=site_subder value=array of extra alias data
   * @param string $aliasGroup Write aliases into a file named group.alias.yml
   */
  function setUpSettings(array $sites, $aliasGroup = 'fixture') {
    foreach ($sites as $subdir => $extra) {
      $this->createSettings($subdir);
    }
    // Create basic site alias data with root and uri
    $siteAliasData = $this->createAliasFileData(array_keys($sites), $aliasGroup);
    // Add in caller-provided site alias data
    $siteAliasData = array_merge_recursive($siteAliasData, $sites);
    $this->writeSiteAliases($siteAliasData, $aliasGroup);
  }

  function createSettings($subdir) {
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
   * Assemble (and optionally install) one or more Drupal sites using a single codebase.
   *
   * It is no longer supported to pass alternative versions of Drupal or an alternative install_profile.
   */
  function setUpDrupal($num_sites = 1, $install = FALSE) {
    $sites_subdirs_all = array('dev', 'stage', 'prod', 'retired', 'elderly', 'dead', 'dust');
    $sites_subdirs = array_slice($sites_subdirs_all, 0, $num_sites);
    $root = $this->webroot();

    // Install (if needed).
    foreach ($sites_subdirs as $subdir) {
      $this->installDrupal($subdir, $install);
    }

    // Write an empty sites.php. Needed for multi-site on D8+.
    if (!file_exists($root . '/sites/sites.php')) {
      copy($root . '/sites/example.sites.php', $root . '/sites/sites.php');
    }

    $siteData = $this->createAliasFile($sites_subdirs, 'unish');
    self::$sites = [];
    foreach ($siteData as $key => $data) {
      self::$sites["unish.$key"] = $data;
    }
    return self::$sites;
  }

  function createAliasFileData($sites_subdirs, $aliasGroup = 'unish') {
    $root = $this->webroot();
    // Stash details about each site.
    $sites = [];
    foreach ($sites_subdirs as $subdir) {
      $sites[$subdir] = array(
        'root' => $root,
        'uri' => $subdir,
        'db_url' => $this->db_url($subdir),
      );
    }
    return $sites;
  }

  function createAliasFile($sites_subdirs, $aliasGroup = 'unish') {
    // Make an alias group for the sites.
    $sites = $this->createAliasFileData($sites_subdirs, $aliasGroup);
    $this->writeSiteAliases($sites, $aliasGroup);

    return $sites;
  }

  /**
   * Install a Drupal site.
   *
   * It is no longer supported to pass alternative versions of Drupal or an alternative install_profile.
   */
  function installDrupal($env = 'dev', $install = FALSE) {
    $root = $this->webroot();
    $uri = $env;
    $site = "$root/sites/$uri";

    // If specified, install Drupal as a multi-site.
    if ($install) {
      $options = array(
        'root' => $root,
        'db-url' => $this->db_url($env),
        'sites-subdir' => $uri,
        'yes' => NULL,
        'quiet' => NULL,
      );
      $this->drush('site-install', array('testing', 'install_configure_form.enable_update_status_emails=NULL'), $options);
      // Give us our write perms back.
      chmod($site, 0777);
    }
    else {
      $this->mkdir($site);
      touch("$site/settings.php");
    }
  }

  /**
   * Write an alias group file and a config file which points to same dir.
   *
   * @param $sites
   */
  function writeSiteAliases($sites, $aliasGroup = 'unish') {
    $this->writeUnishConfig($sites, [], $aliasGroup);
  }

  function writeUnishConfig($unishAliases, $config = [], $aliasGroup = 'unish')
  {
    $etc = self::getSandbox() . '/etc/drush';
    file_put_contents(Path::join($etc, $aliasGroup . '.alias.yml'), Yaml::dump($unishAliases, PHP_INT_MAX, 2));
    $config['drush']['paths']['alias-path'][] = $etc;
    file_put_contents(Path::join($etc, 'drush.yml'), Yaml::dump($config, PHP_INT_MAX, 2));
  }

  /**
   * The sitewide directory for Drupal extensions.
   */
  function drupalSitewideDirectory() {
    return '/sites/all';
  }
}
