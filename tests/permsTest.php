<?php

/*
* @file
*  Permissions test
*/

class permsCase extends Drush_CommandTestCase {
  //
  // Build some permissions scripts and check to see if the output matches
  // the expected result.
  //
  // These tests will pass or fail based solely on the script output; no
  // execution is done.
  //
  function testPermissionsScriptOutput() {
    $default_options = array(
      'pipe' => NULL,
      'dir' => '/srv/www/drupalroot',
      'files' => 'sites/default/files',
      'settings' => 'sites/default/settings.php',
    );
    foreach ($this->testDataForScriptOutput() as $key => $info) {
      $test_options = $info['options'] + $default_options;
      $this->drush('perms', $info['args'], $test_options);
      $actual = $this->getOutput();
      // Remove doubled-up spaces
      $actual = preg_replace('/  +/', ' ', $actual);
      // We build a header to include in assertEquals simply to make it evident
      // which data-driven test failed, should a failure occur.
      $header = "# '$key' Test:\n\$ drush perms " . implode(' ', $info['args']) . $this->buildDescriptiveOptionList($info['options']) . "\n";
      $this->assertEquals($header . trim($info['expected']), $header . trim($actual));
    }
  }


  function testDataForScriptOutput() {
    return array (
      'Default Values' => array(
        'args' => array('owner:group'),
        'options' => array(),
        'expected' => <<<'EOT'

#!/bin/bash
SETTINGS_FILES='0440'
DOC_FILES='0400'
CODE_FILES='0644'
CODE_DIRS='0755'
DATA_FILES='0664'
DATA_DIRS='0775'
CODE_FILES_OWNER='owner'
CODE_FILES_GROUP='group'
DATA_FILES_OWNER='owner'
DATA_FILES_GROUP='group'
find /srv/www/drupalroot -path /srv/www/drupalroot/sites/default/files -prune -o \( \( \! -group $CODE_FILES_GROUP -o \! -user $CODE_FILES_OWNER \) -print0 \) | xargs -0r chown $CODE_FILES_OWNER:$CODE_FILES_GROUP --
find /srv/www/drupalroot -path /srv/www/drupalroot/sites/default/files -prune -o \( -type d \! -perm $CODE_DIRS -print0 \) | xargs -0r chmod $CODE_DIRS --
find /srv/www/drupalroot -path /srv/www/drupalroot/sites/default/files -prune -o \( -type f \! -perm $CODE_FILES \! -path sites/default/settings.php -path '/srv/www/drupalroot/*/*' -print0 \) | xargs -0r chmod $CODE_FILES --
find /srv/www/drupalroot/sites/default/files \( \( \! -group $DATA_FILES_GROUP -o \! -user $DATA_FILES_OWNER \) -print0 \) | xargs -0r chown $DATA_FILES_OWNER:$DATA_FILES_GROUP --
find /srv/www/drupalroot/sites/default/files \( -type d \! -perm $DATA_DIRS -print0 \) | xargs -0r chmod $DATA_DIRS --
find /srv/www/drupalroot/sites/default/files \( -type f \! -perm $DATA_FILES -print0 \) | xargs -0r chmod $DATA_FILES --
chmod $SETTINGS_FILES /srv/www/drupalroot/sites/default/settings.php
find /srv/www/drupalroot -maxdepth 1 \( -type f \! -perm $DOC_FILES \( -path '*.txt' -o -path /srv/www/drupalroot/quickstart.html \) \! -path /srv/www/drupalroot/robots.txt -print0 \) | xargs -0r chmod $DOC_FILES --
find /srv/www/drupalroot -maxdepth 1 \( -type f \! -perm $CODE_FILES \( -path /srv/www/drupalroot/robots.txt -o \! \( -path '*.txt' -o -path /srv/www/drupalroot/quickstart.html \) \) -print0 \) | xargs -0r chmod $CODE_FILES --

EOT

      ),
      'Default Values in "no-variables" mode' => array(
        'args' => array('owner:group'),
        'options' => array('no-variables' => NULL),
        'expected' => <<<'EOT'

#!/bin/bash
find /srv/www/drupalroot \( \( \! -group group -o \! -user owner \) -print0 \) | xargs -0r chown owner:group --
find /srv/www/drupalroot -path /srv/www/drupalroot/sites/default/files -prune -o \( -type d \! -perm 0755 -print0 \) | xargs -0r chmod 0755 --
find /srv/www/drupalroot -path /srv/www/drupalroot/sites/default/files -prune -o \( -type f \! -perm 0644 \! -path sites/default/settings.php -path '/srv/www/drupalroot/*/*' -print0 \) | xargs -0r chmod 0644 --
find /srv/www/drupalroot/sites/default/files \( -type d \! -perm 0775 -print0 \) | xargs -0r chmod 0775 --
find /srv/www/drupalroot/sites/default/files \( -type f \! -perm 0664 -print0 \) | xargs -0r chmod 0664 --
chmod 0440 /srv/www/drupalroot/sites/default/settings.php
find /srv/www/drupalroot -maxdepth 1 \( -type f \! -perm 0400 \( -path '*.txt' -o -path /srv/www/drupalroot/quickstart.html \) \! -path /srv/www/drupalroot/robots.txt -print0 \) | xargs -0r chmod 0400 --
find /srv/www/drupalroot -maxdepth 1 \( -type f \! -perm 0644 \( -path /srv/www/drupalroot/robots.txt -o \! \( -path '*.txt' -o -path /srv/www/drupalroot/quickstart.html \) \) -print0 \) | xargs -0r chmod 0644 --

EOT
      ),
      'Strict with misc excluded' => array(
        'args' => array('owner:group'),
        'options' => array('strict' => NULL, 'exclude' => 'misc'),
        'expected' => <<<'EOT'

#!/bin/bash
SETTINGS_FILES='0440'
DOC_FILES='0400'
CODE_FILES='0640'
CODE_DIRS='0750'
DATA_FILES='0640'
DATA_DIRS='0750'
CODE_FILES_OWNER='owner'
CODE_FILES_GROUP='group'
DATA_FILES_OWNER='owner'
DATA_FILES_GROUP='group'
find /srv/www/drupalroot \( -path /srv/www/drupalroot/sites/default/files -o -path /srv/www/drupalroot/misc \) -prune -o \( \( \! -group $CODE_FILES_GROUP -o \! -user $CODE_FILES_OWNER \) -print0 \) | xargs -0r chown $CODE_FILES_OWNER:$CODE_FILES_GROUP --
find /srv/www/drupalroot \( -path /srv/www/drupalroot/sites/default/files -o -path /srv/www/drupalroot/misc \) -prune -o \( -type d \! -perm $CODE_DIRS -print0 \) | xargs -0r chmod $CODE_DIRS --
find /srv/www/drupalroot \( -path /srv/www/drupalroot/sites/default/files -o -path /srv/www/drupalroot/misc \) -prune -o \( -type f \! -perm $CODE_FILES \! -path sites/default/settings.php -path '/srv/www/drupalroot/*/*' -print0 \) | xargs -0r chmod $CODE_FILES --
find /srv/www/drupalroot/sites/default/files \( \( \! -group $DATA_FILES_GROUP -o \! -user $DATA_FILES_OWNER \) -print0 \) | xargs -0r chown $DATA_FILES_OWNER:$DATA_FILES_GROUP --
find /srv/www/drupalroot/sites/default/files \( -type d \! -perm $DATA_DIRS -print0 \) | xargs -0r chmod $DATA_DIRS --
find /srv/www/drupalroot/sites/default/files \( -type f \! -perm $DATA_FILES -print0 \) | xargs -0r chmod $DATA_FILES --
chmod $SETTINGS_FILES /srv/www/drupalroot/sites/default/settings.php
find /srv/www/drupalroot -maxdepth 1 \( -type f \! -perm $DOC_FILES \( -path '*.txt' -o -path /srv/www/drupalroot/quickstart.html \) \! -path /srv/www/drupalroot/robots.txt -print0 \) | xargs -0r chmod $DOC_FILES --
find /srv/www/drupalroot -maxdepth 1 \( -type f \! -perm $CODE_FILES \( -path /srv/www/drupalroot/robots.txt -o \! \( -path '*.txt' -o -path /srv/www/drupalroot/quickstart.html \) \) -print0 \) | xargs -0r chmod $CODE_FILES --

EOT

      ),
    );
  }

  //
  // Run some permissions tests and check to see if the permissions of
  // the modified folders comes out correctly.
  //
  // Note that all of these tests will run with --skip-set-owner, so
  // that the tests will run without superuser access.  This means that
  // not all available operations are testable by the execution tests.
  //
  function testPermissionsExecution() {
    $testDirName = 'permissionstestdir';
    $src = dirname(__FILE__) . "/$testDirName";
    $testDirectory = UNISH_SANDBOX . "/$testDirName";
    $default_options = array(
      'skip-set-owner' => NULL,
      'dir' => $testDirectory,
      'files' => 'sites/default/files',
      'settings' => 'sites/default/settings.php',
    );
    foreach ($this->testDataForExecution() as $key => $info) {
      if (unish_copy_dir($src, $testDirectory)) {

        $test_options = $info['options'] + $default_options;
        $this->drush('perms', $info['args'], $test_options);
        $actual = $this->getOutput();
        // If the 'pipe' option is set, then we will
        // write the output to a file, execute it, and
        // take the output from the script run as the
        // "actual $actual".
        if (array_key_exists('pipe', $info['options'])) {
          $tmp_script = UNISH_SANDBOX . '/perms_script.sh';
          file_put_contents($tmp_script, $actual);
          chmod($tmp_script, 0777);
          $output = array();
          exec($tmp_script, $output);
          unlink($tmp_script);
          $actual = implode("\n", $output);
        }

        // Remove doubled-up spaces
        $actual = preg_replace('/  +/', ' ', $actual);
        // Replace the test directory with 'DIR'
        $actual = str_replace($testDirectory, 'DIR', $actual);

        if (!array_key_exists('audit', $info['options'])) {
          $files = unish_scan_directory($testDirectory);
          foreach ($files as $filename => $name) {
            $perms = substr(str_pad(base_convert(fileperms($filename), 10, 8), 6, '0', STR_PAD_LEFT), 2);
            $line = $perms . ' ' . str_replace($testDirectory . '/', '', $filename);
            $actual .= "$line\n";
          }
        }

        // We build a header to include in assertEquals simply to make it evident
        // which data-driven test failed, should a failure occur.
        $header = "# '$key' Test:\n\$ drush perms " . implode(' ', $info['args']) . $this->buildDescriptiveOptionList($info['options']) . "\n";
        $this->assertEquals($header . trim($info['expected']), $header . trim($actual));
        unish_file_delete_recursive($testDirectory);
      }
      else {
        // We could not copy our test directory.  Boom.
        $this->assertTrue(FALSE);
      }
    }

  }

  function testDataForExecution() {
    return array (
      'Default Values' => array(
        'args' => array(),
        'options' => array(),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0644 index.php
0400 quickstart.html
0644 robots.txt
0755 includes
0644 includes/bootstrap.inc
0755 misc
0644 misc/drupal.js
0755 sites
0755 sites/default
0440 sites/default/settings.php
0775 sites/default/files
0664 sites/default/files/uploadfile.txt

EOT
      ),
      'Default Values script execution' => array(
        'args' => array(),
        'options' => array('pipe' => NULL),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0644 index.php
0400 quickstart.html
0644 robots.txt
0755 includes
0644 includes/bootstrap.inc
0755 misc
0644 misc/drupal.js
0755 sites
0755 sites/default
0440 sites/default/settings.php
0775 sites/default/files
0664 sites/default/files/uploadfile.txt

EOT
      ),
      'Default Values no-variables script execution' => array(
        'args' => array(),
        'options' => array('pipe' => NULL, 'no-variables' => NULL),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0644 index.php
0400 quickstart.html
0644 robots.txt
0755 includes
0644 includes/bootstrap.inc
0755 misc
0644 misc/drupal.js
0755 sites
0755 sites/default
0440 sites/default/settings.php
0775 sites/default/files
0664 sites/default/files/uploadfile.txt

EOT
      ),
      'Strict' => array(
        'args' => array(),
        'options' => array('strict' => NULL),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0640 index.php
0400 quickstart.html
0640 robots.txt
0750 includes
0640 includes/bootstrap.inc
0750 misc
0640 misc/drupal.js
0750 sites
0750 sites/default
0440 sites/default/settings.php
0750 sites/default/files
0640 sites/default/files/uploadfile.txt

EOT
      ),
      'Lax' => array(
        'args' => array(),
        'options' => array('lax' => NULL),
        'expected' => <<<'EOT'

0664 INSTALL.txt
0664 index.php
0664 quickstart.html
0664 robots.txt
0775 includes
0664 includes/bootstrap.inc
0775 misc
0664 misc/drupal.js
0775 sites
0775 sites/default
0440 sites/default/settings.php
0777 sites/default/files
0666 sites/default/files/uploadfile.txt

EOT
      ),
      'Strict with files excluded' => array(
        'args' => array(),
        'options' => array('strict' => NULL, 'exclude' => 'sites/default/files'),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0640 index.php
0400 quickstart.html
0640 robots.txt
0750 includes
0640 includes/bootstrap.inc
0750 misc
0640 misc/drupal.js
0750 sites
0750 sites/default
0440 sites/default/settings.php
0775 sites/default/files
0664 sites/default/files/uploadfile.txt

EOT
      ),
      # For this test, we redirect 'files' to 'sites' and use
      # 'sites/default/files' as the 'files' subdirectory.
      'Strict with files subdirectory excluded' => array(
        'args' => array(),
        'options' => array('strict' => NULL, 'files' => 'sites', 'exclude' => 'sites/default/files', 'data-dirs' => '555', 'data-files' => '444'),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0640 index.php
0400 quickstart.html
0640 robots.txt
0750 includes
0640 includes/bootstrap.inc
0750 misc
0640 misc/drupal.js
0550 sites
0550 sites/default
0440 sites/default/settings.php
0775 sites/default/files
0664 sites/default/files/uploadfile.txt

EOT
      ),
      'Audit strict with misc excluded' => array(
        'args' => array(),
        'options' => array('strict' => NULL, 'exclude' => 'misc', 'audit' => NULL),
        'expected' => <<<'EOT'

chmod 0750 -- DIR DIR/includes DIR/sites DIR/sites/default DIR/sites/default/files
chmod 0640 -- DIR/includes/bootstrap.inc DIR/sites/default/settings.php DIR/sites/default/files/uploadfile.txt
chmod 0400 -- DIR/INSTALL.txt DIR/quickstart.html
chmod 0640 -- DIR/robots.txt DIR/index.php

EOT
      ),
      'Strict with misc excluded' => array(
        'args' => array(),
        'options' => array('strict' => NULL, 'exclude' => 'misc'),
        'expected' => <<<'EOT'

0400 INSTALL.txt
0640 index.php
0400 quickstart.html
0640 robots.txt
0750 includes
0640 includes/bootstrap.inc
0775 misc
0664 misc/drupal.js
0750 sites
0750 sites/default
0440 sites/default/settings.php
0750 sites/default/files
0640 sites/default/files/uploadfile.txt

EOT
      ),
    );
  }

  // Convert an array of flag => value pairs into --flag=value
  function buildDescriptiveOptionList($options) {
    $descriptiveOutput = '';

    foreach ($options as $key => $value) {
      if (!isset($value)) {
        $descriptiveOutput .= " --$key";
      }
      else {
        $descriptiveOutput .= " --$key=$value";
      }
    }

    return $descriptiveOutput;
  }
}
