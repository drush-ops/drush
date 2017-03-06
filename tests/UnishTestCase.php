<?php

namespace Unish;
use PHPUnit\Framework\TestCase;

abstract class UnishTestCase extends \PHPUnit_Framework_TestCase {

  /**
   * A list of Drupal sites that have been recently installed. They key is the
   * site name and values are details about each site.
   *
   * @var array
   */
  private static $sites = array();

  function __construct($name = NULL, array $data = array(), $dataName = '') {
    parent::__construct($name, $data, $dataName);
  }

  /**
   * We used to assure that each class starts with an empty sandbox directory and
   * a clean environment - http://drupal.org/node/1103568. No need now - hurray!
   */
  public static function setUpBeforeClass() {
    // Avoid perm denied error on Windows by moving out of the dir to be deleted.
    chdir(dirname(UNISH_SANDBOX));
    $sandbox = UNISH_SANDBOX;

    // Clean the global locations.
    $dirs = [getenv('HOME'), $sandbox. '/etc/drush', $sandbox. '/share/drush/commands'];
    foreach ($dirs as $dir) {
      exec('rm -rf .??* '. self::escapeshellarg($dir));
      \unish_mkdir($dir);
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      // Hack to make git use unix line endings on windows
      // We need it to make hashes of files pulled from git match ones hardcoded in tests
      if (!file_exists($sandbox . '\home')) {
        \unish_mkdir($sandbox . '\home');
      }
      exec("git config --file $sandbox\\home\\.gitconfig core.autocrlf false", $output, $return);
    }
    parent::setUpBeforeClass();
  }

  /**
   * Runs after all tests in a class are run. Remove sandbox directory.
   */
  public static function tearDownAfterClass() {
//    chdir(dirname(UNISH_SANDBOX));
//    $dirty = getenv('UNISH_DIRTY');
//    if (file_exists(UNISH_SANDBOX) && empty($dirty)) {
//      \unish_file_delete_recursive(UNISH_SANDBOX, TRUE);
//    }
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

  public function mkdir($path) {
    return \unish_mkdir($path);
  }

  public function recursive_copy($src, $dst) {
    $dir = opendir($src);
    $this->mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
      if (( $file != '.' ) && ( $file != '..' )) {
        if ( is_dir($src . '/' . $file) ) {
          $this->recursive_copy($src . '/' . $file,$dst . '/' . $file);
        }
        else {
          copy($src . '/' . $file,$dst . '/' . $file);
        }
      }
    }
    closedir($dir);
  }

  function webroot() {
    return UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'web';
  }

  function getSites() {
    return self::$sites;
  }

  function directory_cache($subdir = '') {
    return getenv('CACHE_PREFIX') . '/' . $subdir;
  }

  /**
   * @param $env
   * @return string
   */
  function db_url($env) {
    return substr(UNISH_DB_URL, 0, 6) == 'sqlite'  ?  "sqlite://sites/$env/files/unish.sqlite" : UNISH_DB_URL . '/unish_' . $env;
  }

  function db_driver($db_url = UNISH_DB_URL) {
    return parse_url(UNISH_DB_URL, PHP_URL_SCHEME);
  }

  function setUpDrupal($num_sites = 1, $install = FALSE, $version_string = UNISH_DRUPAL_MAJOR_VERSION, $profile = NULL) {
    $sites_subdirs_all = array('dev', 'stage', 'prod', 'retired', 'elderly', 'dead', 'dust');
    $sites_subdirs = array_slice($sites_subdirs_all, 0, $num_sites);
    $root = $this->webroot();
    $major_version = substr($version_string, 0, 1);

    if (!isset($profile)) {
      $profile = $major_version >= 7 ? 'testing' : 'default';
    }
    $db_driver = $this->db_driver(UNISH_DB_URL);

    // Install (if needed).
    foreach ($sites_subdirs as $subdir) {
      $this->installDrupal($subdir, $install, $version_string, $profile);
    }

    // Write an empty sites.php. Needed for multi-site on D8+.
    if ($major_version >= 7 && !file_exists($root . '/sites/sites.php')) {
      copy($root . '/sites/example.sites.php', $root . '/sites/sites.php');
    }

    // Stash details about each site.
    foreach ($sites_subdirs as $subdir) {
      self::$sites[$subdir] = array(
        'root' => $root,
        'uri' => $subdir,
        'db_url' => $this->db_url($subdir),
      );
      // Make an alias for the site
      $this->writeSiteAlias($subdir, $root, $subdir);
    }
    return self::$sites;
  }

  // @todo. It is no longer supported to pass alternative versions of Drush or alternative install_profile.
  function installDrupal($env = 'dev', $install = FALSE, $version_string = UNISH_DRUPAL_MAJOR_VERSION, $profile = NULL, $separate_roots = FALSE) {
    $root = $this->webroot();
    $uri = $separate_roots ? "default" : "$env";
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
      $this->drush('site-install', array($profile), $options);
      // Give us our write perms back.
      chmod($site, 0777);
    }
    else {
      $this->mkdir($site);
      touch("$site/settings.php");
    }
  }

  function writeSiteAlias($name, $root, $uri) {
    $alias_definition = array($name => array('root' => $root,  'uri' => $uri));
    file_put_contents(UNISH_SANDBOX . '/etc/drush/' . $name . '.alias.drushrc.php', $this->unish_file_aliases($alias_definition));
  }

  /**
   * Prepare the contents of an aliases file.
   */
  function unish_file_aliases($aliases) {
    foreach ($aliases as $name => $alias) {
      $records[] = sprintf('$aliases[\'%s\'] = %s;', $name, var_export($alias, TRUE));
    }
    $contents = "<?php\n\n" . implode("\n\n", $records);
    return $contents;
  }

  /**
   * @see drush_drupal_sitewide_directory()
   */
  function drupalSitewideDirectory($major_version = NULL) {
    if (!$major_version) {
      $major_version = UNISH_DRUPAL_MAJOR_VERSION;
    }
    return ($major_version < 8) ? '/sites/all' : '';
  }
}
