<?php

define('UNISH_DRUSH', $_ENV['UNISH_DRUSH']);
define('UNISH_DB_URL', $_ENV['UNISH_DB_URL']);

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
   * Assure that each class starts with an empty sandbox directory.
   */
  public static function setUpBeforeClass() {
    $sandbox = $_ENV['UNISH_SANDBOX'];
    if (file_exists($sandbox)) {
      self::file_delete_recursive($sandbox);
    }
    $ret = mkdir($sandbox);
    // Path must exist before we call realpath().
    define('UNISH_SANDBOX', realpath($sandbox));
    chdir(UNISH_SANDBOX);
  }

  /**
   * Runs after each test case. Remove sandbox directory.
   */
  public static function tearDownAfterClass() {
    if (file_exists(UNISH_SANDBOX)) {
      self::file_delete_recursive(UNISH_SANDBOX);
    }
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
    $cmd[] = $cd ? sprintf('cd %s;', escapeshellarg($cd)) : NULL;
    $cmd[] = UNISH_DRUSH;
    $cmd[] = $site_specification;
    $cmd[] = $command;
    foreach ($args as $arg) {
      $cmd[] = escapeshellarg($arg);
    }
    foreach ($options as $key => $value) {
      if (is_null($value)) {
        $cmd[] = "--$key";
      }
      else {
        $cmd[] = "--$key=$value";
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

  function setUpDrupal($env = 'dev', $install = FALSE, $version_string = '7.x') {
    $root = UNISH_SANDBOX . '/web';
    $this->sites[$env]['root'] = $root;
    $site = "$root/sites/$env";

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
      $this->drush('site-install', array(), $options);
      // Give us our write perms back.
      $ret = chmod($site, 0777);

      // Stash the db_url for this site.
      $this->sites[$env]['db_url'] = UNISH_DB_URL . '/unish_' . $env;
    }
    else {
      mkdir($site);
      touch("$site/settings.php");
    }
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

  function file_delete_recursive($path) {
    return exec('rm -rf ' . escapeshellarg($path));
  }
}