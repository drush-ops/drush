<?php

/*
 * @file
 *   Initialize a sandboxed environment. Starts with call unish_init() at bottom.
 */

abstract class Drush_TestCase extends PHPUnit_Framework_TestCase {

  // Unix exit codes.
  const EXIT_SUCCESS  = 0;
  const EXIT_ERROR = 1;

  /*
   * An array of Drupal sites that are setup in the drush-sandbox.
   */
  var $sites;

  function __construct() {
    $this->_output = false;
  }

  /**
   * Assure that each class starts with an empty sandbox directory and
   * a clean environment - http://drupal.org/node/1103568.
   */
  public static function setUpBeforeClass() {
    $sandbox = UNISH_SANDBOX;
    if (file_exists($sandbox)) {
      unish_file_delete_recursive($sandbox);
    }
    $ret = mkdir($sandbox, 0777, TRUE);
    chdir(UNISH_SANDBOX);

    mkdir(getenv('HOME') . '/.drush', 0777, TRUE);
    mkdir($sandbox . '/etc/drush', 0777, TRUE);
    mkdir($sandbox . '/share/drush/commands', 0777, TRUE);
  }

  /**
   * Runs after each test case. Remove sandbox directory.
   */
  public static function tearDownAfterClass() {
    if (file_exists(UNISH_SANDBOX)) {
      unish_file_delete_recursive(UNISH_SANDBOX);
    }
  }

  public static function is_windows() {
    return (strtoupper(substr(PHP_OS, 0, 3)) == "WIN");
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

    // Escape double quotes.
    $arg = preg_replace('/"/', '\\"', $arg);

    // Escape single quotes.
    $arg = preg_replace('/\'/', '\\\'', $arg);

    // Add surrounding quotes.
    $arg = '"' . $arg . '"';

    return $arg;
  }

  /**
   * Actually runs the command. Does not trap the error stream output as this
   * need PHP 4.3+.
   *
   * @param string $command
   *   The actual command line to run.
   * @return integer
   *   Exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
   */
  function execute($command, $expected_return = self::EXIT_SUCCESS) {
    $this->_output = FALSE;
    // todo check verbose level from phpunit.
    if (TRUE) {
      print "\nExecuting: $command \n";
    }
    exec($command, $this->_output, $return);
    $this->assertEquals($expected_return, $return, 'Unexpected exit code: ' .  $command);
    return $return;
  }

  /**
   * Invoke drush in via execute().
   *
   * @param command
    *   A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
    * @param args
    *   Command arguments.
    * @param $options
    *   An associative array containing options.
    * @param $site_specification
    *   A site alias or site specification. Include the '@' at start of a site alias.
    * @param $cd
    *   A directory to change into before executing.
    * @return integer
    *   An exit code.
    */
  function drush($command, array $args = array(), array $options = array(), $site_specification = NULL, $cd = NULL) {
    $cmd[] = $cd ? sprintf('cd %s;', self::escapeshellarg($cd)) : NULL;
    $cmd[] = UNISH_DRUSH;
    $cmd[] = empty($site_specification) ? NULL : self::escapeshellarg($site_specification);
    $cmd[] = $command;
    if (in_array('--verbose', $_SERVER['argv'])) $args[] = '--verbose';
    if (in_array('--debug', $_SERVER['argv'])) $args[] = '--debug';

    foreach ($args as $arg) {
      $cmd[] = self::escapeshellarg($arg);
    }
    $options['nocolor'] = NULL;
    foreach ($options as $key => $value) {
      if (is_null($value)) {
        $cmd[] = "--$key";
      }
      else {
        $cmd[] = "--$key=" . self::escapeshellarg($value);
      }
    }
    $exec = array_filter($cmd, 'strlen'); // Remove NULLs
    return $this->execute(implode(' ', $exec));
  }

  /**
   *    Accessor for the last output.
   *    @return string        Output as text.
   *    @access public
   */
  function getOutput() {
    return implode("\n", $this->_output);
  }

  /**
   *    Accessor for the last output.
   *    @return array         Output as array of lines.
   *    @access public
   */
  function getOutputAsList() {
    return $this->_output;
  }

  function setUpDrupal($env = 'dev', $install = FALSE, $version_string = '7.x', $profile = NULL) {
    $root = UNISH_SANDBOX . '/web';
    $this->sites[$env]['root'] = $root;
    $site = "$root/sites/$env";
    if (is_null($profile)) {
      $profile = substr($version_string, 0, 1) >= 7 ? 'testing' : 'default';
    }

    // Download Drupal if not already present.
    if (!file_exists($root)) {
      $options = array(
        'destination' => UNISH_SANDBOX,
        'drupal-project-rename' => 'web',
        'yes' => NULL,
        'quiet' => NULL,
      );
      $this->drush('pm-download', array("drupal-$version_string"), $options);
    }

    // If specified, install Drupal as a multi-site.
    if ($install) {
      $options = array(
        'root' => $root,
        'db-url' => UNISH_DB_URL . '/unish_' . $env,
        'sites-subdir' => $env,
        'yes' => NULL,
        'quiet' => NULL,
      );
      $this->drush('site-install', array($profile), $options);
      // Give us our write perms back.
      $ret = chmod($site, 0777);

      // Stash the db_url for this site.
      $this->sites[$env]['db_url'] = UNISH_DB_URL . '/unish_' . $env;
    }
    else {
      mkdir($site);
      touch("$site/settings.php");
    }
    // Make an alias for the site
    $alias_definition = array($env => array('root' => $root,  'uri' => $env));
    file_put_contents(UNISH_SANDBOX . '/etc/drush/' . $env . '.alias.drushrc.php', $this->file_aliases($alias_definition));
  }

  // Copied from D7 - profiles/standard/standard.install
  function create_node_types_php() {
    $php = "
      \$types = array(
        array(
          'type' => 'page',
          'name' => 'Basic page',
          'base' => 'node_content',
          'description' => 'Use <em>basic pages</em> for your static content, such as an \'About us\' page.',
          'custom' => 1,
          'modified' => 1,
          'locked' => 0,
        ),
        array(
          'type' => 'article',
          'name' => 'Article',
          'base' => 'node_content',
          'description' => 'Use <em>articles</em> for time-sensitive content like news, press releases or blog posts.',
          'custom' => 1,
          'modified' => 1,
          'locked' => 0,
        ),
      );

      foreach (\$types as \$type) {
        \$type = node_type_set_defaults(\$type);
        node_type_save(\$type);
        node_add_body_field(\$type);
      }
    ";
    return $php;
  }

  /*
   * Prepare the contents of an aliases file.
   */
  function file_aliases($aliases) {
    foreach ($aliases as $name => $alias) {
      $records[] = sprintf('$aliases[\'%s\'] = %s;', $name, var_export($alias, TRUE));
    }
    $contents = "<?php\n\n" . implode("\n\n", $records);
    return $contents;
  }

  /**
   * Same code as drush_delete_dir().
   * @see drush_delete_dir()
   *
   * @param string $dir
   * @return boolean
   */
  function file_delete_recursive($dir) {
    if (!file_exists($dir)) {
      return TRUE;
    }
    if (!is_dir($dir)) {
      @chmod($dir, 0777); // Make file writeable
      return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
      if ($item == '.' || $item == '..') {
        continue;
      }
      if (!self::file_delete_recursive($dir.'/'.$item)) {
        return FALSE;
      }
    }
    return rmdir($dir);
  }
}

/*
 * Initialize our environment at he start of each run (i.e. suite).
 */
function unish_init() {
  // We read from globals here because env can be empty and ini did not work in quick test.
  define('UNISH_DB_URL', getenv('UNISH_DB_URL') ? getenv('UNISH_DB_URL') : !empty($GLOBALS['UNISH_DB_URL']) ? $GLOBALS['UNISH_DB_URL'] : 'mysql://root:@127.0.0.1');

  // UNISH_DRUSH value can come from phpunit.xml or `which drush`.
  if (!defined('UNISH_DRUSH')) {
    // Let the UNISH_DRUSH environment variable override if set.
    $unish_drush = isset($_SERVER['UNISH_DRUSH']) ? $_SERVER['UNISH_DRUSH'] : NULL;
    $unish_drush = isset($GLOBALS['UNISH_DRUSH']) ? $GLOBALS['UNISH_DRUSH'] : $unish_drush;
    if (empty($unish_drush)) {
      $unish_drush = Drush_TestCase::is_windows() ? exec('for %i in (drush) do @echo.   %~$PATH:i') : trim(`which drush`);
    }
    define('UNISH_DRUSH', $unish_drush);
  }

  define('UNISH_TMP', getenv('UNISH_TMP') ? getenv('UNISH_TMP') : (isset($GLOBALS['UNISH_TMP']) ? $GLOBALS['UNISH_TMP'] : sys_get_temp_dir()));
  define('UNISH_SANDBOX', UNISH_TMP . '/drush-sandbox');

  $home = UNISH_SANDBOX . '/home';
  putenv("HOME=$home");
  putenv("HOMEDRIVE=$home");

  putenv('ETC_PREFIX=' . UNISH_SANDBOX);
  putenv('SHARE_PREFIX=' . UNISH_SANDBOX);

  // Cache dir lives outside the sandbox so that we get persistence across classes.
  $cache = UNISH_TMP . '/drush_cache';
  putenv("CACHE_PREFIX=" . $cache);
  // Wipe at beginning of run.
  if (file_exists($cache)) {
    unish_file_delete_recursive($cache);
  }
}

/**
  * Same code as drush_delete_dir().
  * @see drush_delete_dir()
  *
  * @param string $dir
  * @return boolean
  */
 function unish_file_delete_recursive($dir) {
   if (!file_exists($dir)) {
     return TRUE;
   }
   if (!is_dir($dir)) {
     @chmod($dir, 0777); // Make file writeable
     return unlink($dir);
   }
   foreach (scandir($dir) as $item) {
     if ($item == '.' || $item == '..') {
       continue;
     }
     if (!unish_file_delete_recursive($dir.'/'.$item)) {
       return FALSE;
     }
   }
   return rmdir($dir);
 }

 // This code is in global scope.
 // TODO: I would rather this code at top of file, but I get Fatal error: Class 'Drush_TestCase' not found
 unish_init();
