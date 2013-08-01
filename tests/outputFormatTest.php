<?php

/**
 * @file
 *   Tests for outputformat.drush.inc
 */

/**
 * @todo, Consider removing these tests now that we have outputFormatUnitCase.
 *
 *  @group slow
 *  @group commands
 */
class outputFormatCase extends Drush_CommandTestCase {

/**
 * Test output formats using various Drush commands on a Drupal site.
 *
 * Cannot use dataProvider since we want to share one setUpDrupal(),
 **/
  public function testOutputFormatWithDrupal() {
    $data = $this->getDataForDrupal();
    $sites = $this->setUpDrupal(1, TRUE);
    $site_options = array(
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    $this->drush('pm-download', array('devel'), $site_options + array('cache' => NULL, 'skip' => TRUE));

    foreach ($data as $row) {
      extract($row);
      $name = $name . ": ";
      // We need to specify a fixed column width so that word wrapping does
      // not change our output contrary to our expectations when run in
      // a narrow terminal window.
      $env = array(
        'COLUMNS' => '120',
      );
      $this->drush($command, $args, $site_options + $options + array('format' => $format), NULL, NULL, self::EXIT_SUCCESS, NULL, $env);
      $output = trim($this->getOutput()); // note: we consider trailing eols insignificant
      // If the Drupal command we are running might produce variable output,
      // we can use one or more output filters to simplify the output down
      // to an invariant form.
      foreach ($output_filter as $regex => $replacement) {
        $output = preg_replace($regex, $replacement, $output);
      }
      $this->assertEquals($name . $expected, $name. $output);
    }
  }

  public function getDataForDrupal() {
    return array(
      array(
        'name' => 'Status test - drush version / list',
        'command' => 'core-status',
        'args' => array('drush version'),
        'options' => array(),
        'format' => 'list',
        'output_filter' => array('/[0-9]+\.[0-9]+[a-zA-Z0-9-]*/' => '0.0-dev'),
        'expected' => '0.0-dev',
      ),
//      array(
//        'name' => 'Status test - drush / ini',
//        'command' => 'core-status',
//        'args' => array('drush'),
//        'format' => 'ini',
//        'output_filter' => array('/[0-9]+\.[0-9]+[a-zA-Z0-9-]*/' => '0.0-dev', '#/.*/etc/drush#' => '/etc/drush'),
//        'expected' => 'drush-version=0.0-dev
//drush-conf=
//drush-alias-files=/etc/drush/dev.alias.drushrc.php',
//      ),
      array(
        'name' => 'Status test - drush / export',
        'command' => 'core-status',
        'args' => array('drush'),
        'options' => array(),
        'format' => 'var_export',
        'output_filter' => array('/[0-9]+\.[0-9]+[a-zA-Z0-9-]*/' => '0.0-dev', '#/.*/etc/drush#' => '/etc/drush'),
        'expected' => "array(
  'drush-version' => '0.0-dev',
  'drush-conf' => array(),
  'drush-alias-files' => array(
    '/etc/drush/dev.alias.drushrc.php',
  ),
)",
      ),
      array(
        'name' => 'Status test - drush / key-value',
        'command' => 'core-status',
        'args' => array('drush'),
        'options' => array(),
        'format' => 'key-value',
        'output_filter' => array('/[0-9]+\.[0-9]+[a-zA-Z0-9-]*/' => '0.0-dev', '#/.*/etc/drush#' => '/etc/drush'),
        'expected' => "Drush version         :  0.0-dev
 Drush configuration   :
 Drush alias files     :  /etc/drush/dev.alias.drushrc.php",
      ),
      /*
        core-requirements is a little hard to test, because the
        output can be quite variable

      array(
        'name' => 'Requirements test - table',
        'command' => 'core-requirements',
        'args' => array(),
        'format' => 'table',
        'output_filter' => array(),
        'expected' => "",
      ),
      */
//      array(
//        'name' => 'pm-updatestatus - table',
//        'command' => 'pm-updatestatus',
//        'args' => array(),
//        'options' => array(),
//        'format' => 'table',
//        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0', '/Update available/' => 'Up to date'),
//        'expected' => "Name    Installed Version  Proposed version  Message
// Drupal  0.0               0.0              Up to date",
//      ),
      /*
        pm-updatestatus --format=csv does not work.

        should filter out the items that do not need updating, and print just
        the name of the projects that need updates

      array(
        'name' => 'pm-updatestatus - csv',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'csv',
        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "",
      ),
      */
// updatestatus now omits projects that are up tp date. this test now needs work.
//      array(
//        'name' => 'pm-updatestatus - csv',
//        'command' => 'pm-updatestatus',
//        'args' => array(),
//        'options' => array(),
//        'format' => 'csv',
//        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0', '/Update available/' => 'Up to date'),
//        'expected' => "drupal,0.0,0.0,Up to date",
//      ),
      /*
        pm-updatestatus --format=ini does not work

      array(
        'name' => 'pm-updatestatus - ini',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'ini',
        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "",
      ),
      */
//      array(
//        'name' => 'pm-updatestatus - ini-sections',
//        'command' => 'pm-updatestatus',
//        'args' => array(),
//        'format' => 'ini-sections',
//        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0', '/Update available/' => 'Up to date'),
//        'expected' => "[drupal]
//short_name=drupal
//installed_version=0.0
//proposed_version=0.0
//message=Up to date",
//      ),
      /*
        pm-updatestatus --format=key-value does not work

      array(
        'name' => 'pm-updatestatus - key-value',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'options' => array(),
        'format' => 'key-value',
        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "",
      ),
      */
//      array(
//        'name' => 'pm-updatestatus - key-value-list',
//        'command' => 'pm-updatestatus',
//        'args' => array(),
//        'options' => array(),
//        'format' => 'key-value-list',
//        'output_filter' => array('/[0-9]+\.[0-9]+/' => '0.0', '/Update available/' => 'Up to date'),
//        'expected' => "Name                :  Drupal
// Installed Version   :  0.0
// Proposed version    :  0.0
// Message             :  Up to date",
//      ),
      array(
        'name' => 'pm-info - key-value-list',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'key-value-list',
        'output_filter' => array(),
        'expected' => "Project       :  devel
 Type          :  module
 Description   :  Various blocks, pages, and functions for developers.",
      ),
      array(
        'name' => 'pm-info - csv',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'csv',
        'expected' => "devel,module,\"Various blocks, pages, and functions for developers.\"",
      ),
      /*
         pm-info --format=ini does not work.

         The output data could not be processed by the selected format 'string'.
         Multiple rows provided where only one is allowed.

      array(
        'name' => 'pm-info - ini',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'ini',
        'expected' => "",
      ),
      */
//      array(
//        'name' => 'pm-info - ini-sections',
//        'command' => 'pm-info',
//        'args' => array('devel'),
//        'options' => array('fields' => 'project,type,devel,description'),
//        'format' => 'ini-sections',
//        'expected' => "[devel]
//project=devel
//type=module
//description=Various blocks, pages, and functions for developers.",
//      ),
      /*
        pm-info --format=key-value does not respect --fields

      array(
        'name' => 'pm-info - key-value',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'key-value',
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-info - table',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,description'),
        'format' => 'table',
        'output_filter' => array(),
        'expected' => "Project  Type    Description
 devel    module  Various blocks, pages, and functions for developers.",
      ),

      // More commands that also support output formats:

      // pm-list
      // queue-list
      // cache-get
      // config-get and config-list (D8 only)
      // field-info
      // php-eval
      // role-list
      // search-status
      // site-alias
      // user-information
      // variable-get
      // version
      // watchdog-list
      // watchdog-show

    );
  }
}
