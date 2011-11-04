<?php

/**
 * Make makefile tests.
 *
 * The makefiles for testing are located in commands/make/tests
 * TODO move these to a subdirectory here?
 */
class makeMakefileCase extends Drush_CommandTestCase {
  public function testMakeMakefile() {
    $makefiles = $this->getMakefiles();
    $default_options = array(
      'test' => NULL,
      'md5' => 'print',
    );
    $makefile_path = dirname(__FILE__) . '/makefiles';
    foreach ($makefiles as $type => $config) {
      $options = array_merge($config['options'], $default_options);
      $makefile = $makefile_path . '/' . $config['makefile'];
      $return = !empty($config['fail']) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
      $this->drush('make', array($makefile), $options, NULL, NULL, $return);

      // Check the log for the build hash.
      $output = $this->getOutputAsList();
      $this->assertEquals($output[0], $config['md5'], $config['name'] . ' - build md5 matches expected value: ' . $config['md5']);
    }
  }

  function getMakefiles() {
    return array(
      'cvs' => array(
        'name'     => 'CVS integration',
        'makefile' => 'cvs.make',
        'build'    => TRUE,
        'md5' => 'd46e1c48b17971637dcd89c1b5881bf7',
        'options'  => array('no-core' => NULL),
      ),
      'get' => array(
        'name'     => 'Test GET retrieval of projects',
        'makefile' => 'get.make',
        'build'    => TRUE,
        'md5' => '4bf18507da89bed601548210c22a3bed',
        'options'  => array('no-core' => NULL),
      ),
      'post' => array(
        'name'     => 'Test POST retrieval of projects',
        'makefile' => 'post.make',
        'build'    => TRUE,
        'md5' => '6a50624cbd65cc69011ae6c089ce298a',
        'options'  => array('no-core' => NULL),
      ),
      'git' => array(
        'name'     => 'GIT integration',
        'makefile' => 'git.make',
        'build'    => TRUE,
        'md5' => 'ac0899a193835f7e93af812dae4e2c58',
        'options'  => array('no-core' => NULL),
      ),
      'no-patch-txt' => array(
        'name'     => 'Test --no-patch-txt option',
        'makefile' => 'patches.make',
        'build'    => TRUE,
        'md5' => 'e43b25505a5edfcdf25b4eaa064978b2',
        'options'  => array('no-core' => NULL, 'no-patch-txt' => NULL),
      ),
      'patch' => array(
        'name'     => 'Test patching and writing of PATCHES.txt file',
        'makefile' => 'patches.make',
        'build'    => TRUE,
        'md5' => '27403b34b599af1cbdb50417e6ea626f',
        'options'  => array('no-core' => NULL),
      ),
      'include' => array(
        'name'     => 'Including files and property overrides',
        'makefile' => 'include.make',
        'build'    => TRUE,
        'md5' => 'e2e230ec5eccaf5618050559ab11510d',
        'options'  => array(),
      ),
      'recursion' => array(
        'name'     => 'Recursion',
        'makefile' => 'recursion.make',
        'build'    => TRUE,
        'md5' => '1fb4b3b3f07d8ea7bacaf1c8c2c1e7a9',
        'options'  => array('no-core' => NULL),
      ),
      'svn' => array(
        'name'     => 'SVN',
        'makefile' => 'svn.make',
        'build'    => TRUE,
        'md5' => '0cb28a15958d7fc4bbf8bf6b00bc6514',
        'options'  => array('no-core' => NULL),
      ),
      'bzr' => array(
        'name'     => 'Bzr',
        'makefile' => 'bzr.make',
        'build'    => TRUE,
        'md5' => '272e2b9bb27794c54396f2f03c159725',
        'options'  => array(),
      ),
      'translations' => array(
        'name'     => 'Translation downloads',
        'makefile' => 'translations.make',
        'build'    => TRUE,
        'md5' => 'd53de1aa4ed6115d928b7079ee7c5b57',
        'options'  => array('translations' => 'es,pt-br'),
      ),
      'contrib-destination' => array(
        'name'     => 'Contrib-destination attribute',
        'makefile' => 'contrib-destination.make',
        'build'    => TRUE,
        'md5' => 'd615d004adfa8ebfe44e91119b88389c',
        'options'  => array('no-core' => NULL, 'contrib-destination' => '.'),
      ),
      'file' => array(
        'name'     => 'File extraction',
        'makefile' => 'file.make',
        'build'    => TRUE,
        'md5' => 'f76ec174a775ce67f8e9edcb02336ef2',
        'options'  => array('no-core' => NULL),
      ),
      'md5-succeed' => array(
        'name'     => 'MD5 validation',
        'makefile' => 'md5-succeed.make',
        'build'    => TRUE,
        'md5' => 'f76ec174a775ce67f8e9edcb02336ef2',
        'options'  => array('no-core' => NULL),
      ),
      'md5-fail' => array(
        'name'     => 'Failed MD5 validation test',
        'makefile' => 'md5-fail.make',
        'build'    => FALSE,
        'md5' => FALSE,
        'options'  => array('no-core' => NULL),
        'fail' => TRUE,
      ),
      'ignore-checksums' => array(
        'name'     => 'Ignore invalid checksum/s',
        'makefile' => 'md5-fail.make',
        'build'    => TRUE,
        'md5' => 'f76ec174a775ce67f8e9edcb02336ef2',
        'options'  => array('no-core' => NULL, 'ignore-checksums' => NULL),
      ),
/* TODO get d.o. tests working. Basically needs expected fail
 * functionality in the above test runner
      'do-fail-attribute' => array(
        'name'     => 'D.o: Fail attributes',
        'makefile' => 'do-fail-attribute.make',
        'build'    => FALSE,
        'options'  => array('drupal-org' => NULL),
      ),
      'do-fail-dev' => array(
        'name'     => 'D.o: Fail dev',
        'makefile' => 'do-fail-dev.make',
        'build'    => FALSE,
        'options'  => array('drupal-org' => NULL),
      ),
      'do-fail-library' => array(
        'name'     => 'D.o: Fail library',
        'makefile' => 'do-fail-library.make',
        'build'    => FALSE,
        'options'  => array('drupal-org' => NULL),
      ),
      'do-fail-patch' => array(
        'name'     => 'D.o: Fail patch',
        'makefile' => 'do-fail-patch.make',
        'build'    => FALSE,
        'options'  => array('drupal-org' => NULL),
      ),
      'do-fail-version' => array(
        'name'     => 'D.o: Fail version',
        'makefile' => 'do-fail-version.make',
        'build'    => FALSE,
        'options'  => array('drupal-org' => NULL),
      ),
      'do-succeed' => array(
        'name'     => 'D.o: Success',
        'makefile' => 'do-succeed.make',
        'build'    => TRUE,
        'md5' => 'fc3cedb0f656a4d9bc071815e4ca2e07',
        'options'  => array('drupal-org' => NULL),
      ),
*/
    );
  }
}