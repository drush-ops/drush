<?php

namespace Unish;

/**
 * Make makefile tests.
 * @group make
 * @group slow
 */
class makeMakefileCase extends CommandUnishTestCase {
  /**
   * Path to test make files.
   */
  protected $makefile_path;

  /**
   * Initialize $makefile_path.
   */
  function __construct() {
    $this->makefile_path =  dirname(__FILE__) . DIRECTORY_SEPARATOR . 'makefiles';
  }

  /**
   * Run a given makefile test.
   *
   * @param $test
   *   The test makefile to run, as defined by $this->getMakefile();
   */
  private function runMakefileTest($test) {
    $default_options = array(
      'test' => NULL,
      'md5' => 'print',
    );
    $config = $this->getMakefile($test);
    $options = array_merge($config['options'], $default_options);
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $return = !empty($config['fail']) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
    $this->drush('make', array($makefile), $options, NULL, NULL, $return);

    // Check the log for the build hash if this test should pass.
    if (empty($config['fail'])) {
      $output = $this->getOutput();
      $this->assertContains($config['md5'], $output, $config['name'] . ' - build md5 matches expected value: ' . $config['md5']);
    }
  }

  function getMakefile($key) {
    static $tests;
    $tests = $this->listMakefileTests();
    return $tests[$key];
  }

  function listMakefileTests() {
    $tests = array(
      'bzr' => array(
        'name'     => 'Bzr',
        'makefile' => 'bzr.make',
        'build'    => TRUE,
        'md5' => '272e2b9bb27794c54396f2f03c159725',
        'options'  => array(),
      ),
      'bz2' => array(
        'name'     => 'bzip2',
        'makefile' => 'bz2.make',
        'build'    => TRUE,
        'md5'      => '5ec081203131a1a3277c8b23f9ddb995',
        'options'  => array('no-core' => NULL),
      ),
      'bz2-singlefile' => array(
        'name'     => 'bzip2 single file',
        'makefile' => 'bz2-singlefile.make',
        'build'    => TRUE,
        'md5'      => '4f9d57f6caaf6ece0526d867327621cc',
        'options'  => array('no-core' => NULL),
      ),
      'contrib-destination' => array(
        'name'     => 'Contrib-destination attribute',
        'makefile' => 'contrib-destination.make',
        'build'    => TRUE,
        'md5' => '2aed36201ede1849ce43d9b7d6f7e9e1',
        'options'  => array('no-core' => NULL, 'contrib-destination' => '.'),
      ),
      'defaults' => array(
        'name'     => 'Test defaults array.',
        'makefile' => 'defaults.make',
        'build'    => TRUE,
        'md5' => 'e6c0d6b37cd8573cbd188742b95a274e',
        'options'  => array('no-core' => NULL, 'contrib-destination' => '.'),
      ),
      'file' => array(
        'name'     => 'File extraction',
        'makefile' => 'file.make',
        'build'    => TRUE,
        'md5' => '4e9883d6f9f6572af287635689c2545d',
        'options'  => array('no-core' => NULL),
      ),
      'file-extract' => array(
        'name'     => 'Extract archives',
        'makefile' => 'file-extract.make',
        'build'    => TRUE,
        'md5' => 'b43d271ab3510eb33c1e300c78893458',
        // @todo This test often fails with concurrency set to more than one.
        'options'  => array('no-core' => NULL, 'concurrency' => 1),
      ),
      'get' => array(
        'name'     => 'Test GET retrieval of projects',
        'makefile' => 'get.make',
        'build'    => TRUE,
        'md5' => '4bf18507da89bed601548210c22a3bed',
        'options'  => array('no-core' => NULL),
      ),
      'git' => array(
        'name'     => 'GIT integration',
        'makefile' => 'git.make',
        'build'    => TRUE,
        'md5' => '4c80d78b50c89b5ba11a997bafec2b43',
        'options'  => array('no-core' => NULL, 'no-gitinfofile' => NULL),
      ),
      'git-simple' => array(
        'name' => 'Simple git integration',
        'makefile' => 'git-simple.make',
        'build' => TRUE,
        'md5' => '0147681209adef163a8ac2c0cff2a07e',
        'options'  => array('no-core' => NULL, 'no-gitinfofile' => NULL),
      ),
      'git-simple-8' => array(
        'name' => 'Simple git integration for D8',
        'makefile' => 'git-simple-8.make',
        'build' => TRUE,
        'options'  => array('no-core' => NULL),
      ),
      'gzip' => array(
        'name'     => 'gzip',
        'makefile' => 'gzip.make',
        'build'    => TRUE,
        'md5'      => '25b514df18a87b655437388af083e22c',
        'options'  => array('no-core' => NULL),
      ),
      'ignore-checksums' => array(
        'name'     => 'Ignore invalid checksum/s',
        'makefile' => 'md5-fail.make',
        'build'    => TRUE,
        'md5' => 'f76ec174a775ce67f8e9edcb02336ef2',
        'options'  => array('no-core' => NULL, 'ignore-checksums' => NULL),
      ),
      'include' => array(
        'name'     => 'Including files and property overrides',
        'makefile' => 'include.make',
        'build'    => TRUE,
        'md5' => 'e2e230ec5eccaf5618050559ab11510d',
        'options'  => array(),
      ),
      'includes-git' => array(
        'name'     => 'Including makefiles from remote repositories',
        'makefile' => 'includes-main.make',
        'build'    => TRUE,
        'options'  => array(),
      ),
      'limit-libraries' => array(
        'name'     => 'Limit libraries downloaded',
        'makefile' => 'limited-projects-libraries.make',
        'build'    => TRUE,
        'md5' => 'cb0da4465d86eb34cafb167787862eb6',
        'options'  => array('no-core' => NULL, 'libraries' => 'drush_make'),
      ),
      'limit-libraries-multiple' => array(
        'name'     => 'Limit multiple libraries downloaded',
        'makefile' => 'limited-projects-libraries.make',
        'build'    => TRUE,
        'md5' => '7c10e6fc65728a77a2b0aed4ec2a29cd',
        'options'  => array('no-core' => NULL, 'libraries' => 'drush_make,token'),
      ),
      'limit-projects' => array(
        'name'     => 'Limit projects downloaded',
        'makefile' => 'limited-projects-libraries.make',
        'build'    => TRUE,
        'md5' => '3149650120e541d7d0fa577eef0ee9a3',
        'options'  => array('no-core' => NULL, 'projects' => 'boxes'),
      ),
      'limit-projects-multiple' => array(
        'name'     => 'Limit multiple projects downloaded',
        'makefile' => 'limited-projects-libraries.make',
        'build'    => TRUE,
        'md5' => 'ef8996c4d6c6f0d229e2237c73860071',
        'options'  => array('no-core' => NULL, 'projects' => 'boxes,admin_menu'),
      ),
      'md5-fail' => array(
        'name'     => 'Failed MD5 validation test',
        'makefile' => 'md5-fail.make',
        'build'    => FALSE,
        'md5' => FALSE,
        'options'  => array('no-core' => NULL),
        'fail' => TRUE,
      ),
      'md5-succeed' => array(
        'name'     => 'MD5 validation',
        'makefile' => 'md5-succeed.make',
        'build'    => TRUE,
        'md5' => 'f76ec174a775ce67f8e9edcb02336ef2',
        'options'  => array('no-core' => NULL),
      ),
      'no-patch-txt' => array(
        'name'     => 'Test --no-patch-txt option',
        'makefile' => 'patches.make',
        'build'    => TRUE,
        'md5' => '59267a04f98374ed5b0b75e90cefcd9c',
        'options'  => array('no-core' => NULL, 'no-patch-txt' => NULL),
      ),
      'options-array' => array(
        'name'     => 'Test global options array',
        'makefile' => 'options-array.make',
        'build'    => TRUE,
        'options'  => array(),
      ),
      'options-project' => array(
        'name'     => 'Test per-project options array',
        'makefile' => 'options-project.make',
        'build'    => TRUE,
        'options'  => array(),
      ),
      'patch' => array(
        'name'     => 'Test patching and writing of PATCHES.txt file',
        'makefile' => 'patches.make',
        'build'    => TRUE,
        'md5' => '536ee287344c24f47e0808622d7d091b',
        'options'  => array('no-core' => NULL),
      ),
      'recursion' => array(
        'name'     => 'Recursion',
        'makefile' => 'recursion.make',
        'build'    => TRUE,
        'md5' => 'cd095bd6dadb2f0d3e81f85f13685372',
        'options'  => array(
          'no-core' => NULL,
          'contrib-destination' => 'profiles/drupal_forum',
        ),
      ),
      'recursion-override' => array(
        'name' => 'Recursion overrides',
        'makefile' => 'recursion-override.make',
        'build' => TRUE,
        'md5' => 'a13c3d5d416be9fa78569514844b96a2',
        'options' => array(
          'no-core' => NULL,
        ),
      ),
      'subtree' => array(
        'name'     => 'Use subtree from downloaded archive',
        'makefile' => 'subtree.make',
        'build'    => TRUE,
        'md5' => 'db3770d8b4c9ce77510cbbcc566da9b8',
        'options'  => array('no-core' => NULL),
      ),
      'svn' => array(
        'name'     => 'SVN',
        'makefile' => 'svn.make',
        'build'    => TRUE,
        'md5' => '0cb28a15958d7fc4bbf8bf6b00bc6514',
        'options'  => array('no-core' => NULL),
      ),
      'translations' => array(
        'name'     => 'Translation downloads',
        'makefile' => 'translations.make',
        'options'  => array(
          'translations' => 'es,pt-br',
          'no-core' => NULL,
        ),
      ),
      'translations-inside' => array(
        'name'     => 'Translation downloads inside makefile',
        'makefile' => 'translations-inside.make',
      ),
      'translations-inside7' => array(
        'name'     => 'Translation downloads inside makefile, core 7.x',
        'makefile' => 'translations-inside7.make',
      ),
      'use-distribution-as-core' => array(
        'name'     => 'Use distribution as core',
        'makefile' => 'use-distribution-as-core.make',
        'build'    => TRUE,
        'md5' => '643a603025a20d498eb583a1e7970bad',
        'options'  => array(),
      ),
    );
    // Replicate ini tests for YAML format.
    foreach ($tests as $id => $test) {
      $id_yaml = $id  . '-yaml';
      $tests[$id_yaml] = $test;
      $tests[$id_yaml]['name'] = $tests[$id]['name'] . '(in YAML format)';
      $tests[$id_yaml]['makefile'] = $tests[$id]['makefile'] . '.yml';
    }
    return $tests;
  }

  /************************************************************************
   *                                                                      *
   *  List of make tests (in alphabetical order, for easier navigation.)  *
   *                                                                      *
   ************************************************************************/

  /**
   * Test .info file writing and the use of a git reference cache for
   * git downloads.
   */
  function testInfoFileWritingGit() {
    // Use the git-simple.make file.
    $config = $this->getMakefile('git-simple');

    $options = array('no-core' => NULL);
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $this->drush('make', array($makefile, UNISH_SANDBOX . '/test-build'), $options);

    // Test cck_signup.info file.
    $this->assertFileExists(UNISH_SANDBOX . '/test-build/sites/all/modules/cck_signup/cck_signup.info');
    $contents = file_get_contents(UNISH_SANDBOX . '/test-build/sites/all/modules/cck_signup/cck_signup.info');
    $this->assertContains('; Information added by drush on 2011-07-27', $contents);
    $this->assertContains('version = "2fe932c"', $contents);
    $this->assertContains('project = "cck_signup"', $contents);

    // Verify that a reference cache was created.
    $cache_dir = UNISH_CACHE . DIRECTORY_SEPARATOR . 'cache';
    $this->assertFileExists($cache_dir . '/git/cck_signup-' . md5('http://git.drupal.org/project/cck_signup.git'));

    // Test context_admin.info file.
    $this->assertFileExists(UNISH_SANDBOX . '/test-build/sites/all/modules/context_admin/context_admin.info');
    $contents = file_get_contents(UNISH_SANDBOX . '/test-build/sites/all/modules/context_admin/context_admin.info');
    $this->assertContains('; Information added by drush on 2011-10-27', $contents);
    $this->assertContains('version = "eb9f05e"', $contents);
    $this->assertContains('project = "context_admin"', $contents);

    // Verify git reference cache exists.
    $this->assertFileExists($cache_dir . '/git/context_admin-' . md5('http://git.drupal.org/project/context_admin.git'));

    // Text caption_filter .info rewrite.
    $this->assertFileExists(UNISH_SANDBOX . '/test-build/sites/all/modules/contrib/caption_filter/caption_filter.info');
    $contents = file_get_contents(UNISH_SANDBOX . '/test-build/sites/all/modules/contrib/caption_filter/caption_filter.info');
    $this->assertContains('; Information added by drush on 2011-09-20', $contents);
    $this->assertContains('version = "7.x-1.2+0-dev"', $contents);
    $this->assertContains('project = "caption_filter"', $contents);
  }

  /**
   * Test .info file writing and the use of a git reference cache for
   * git downloads.
   */
  function testInfoYamlFileWritingGit() {
    // Use the Drupal 8 .make file.
    $config = $this->getMakefile('git-simple-8');

    $options = array('no-core' => NULL);
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $this->drush('make', array($makefile, UNISH_SANDBOX . '/test-build'), $options);

    $this->assertFileExists(UNISH_SANDBOX . '/test-build/modules/honeypot/honeypot.info.yml');
    $contents = file_get_contents(UNISH_SANDBOX . '/test-build/modules/honeypot/honeypot.info.yml');
    $this->assertContains('# Information added by drush on 2015-09-03', $contents);
    $this->assertContains("version: '8.x-1.x-dev'", $contents);
    $this->assertContains("project: 'honeypot'", $contents);
  }

  function testMakeBzr() {
    // Silently skip bzr test if bzr is not installed.
    exec('which bzr', $output, $whichBzrErrorCode);
    if (!$whichBzrErrorCode) {
      $this->runMakefileTest('bzr');
    }
    else {
      $this->markTestSkipped('bzr command is not available.');
    }
  }

  function testMakeBZ2() {
    // Silently skip bz2 test if bz2 is not installed.
    exec('which bzip2', $output, $whichBzip2ErrorCode);
    if (!$whichBzip2ErrorCode) {
      $this->runMakefileTest('bz2');
    }
    else {
      $this->markTestSkipped('bzip2 command not available.');
    }
  }

  /* TODO: http://download.gna.org/wkhtmltopdf/obsolete/linux/wkhtmltopdf-0.11.0_rc1-static-amd64.tar.bz2 cannot be downloaded any longer
  function testMakeBZ2SingleFile() {
    // Silently skip bz2 test if bz2 is not installed.
    exec('which bzip2', $output, $whichBzip2ErrorCode);
    if (!$whichBzip2ErrorCode) {
      $this->runMakefileTest('bz2-singlefile');
    }
    else {
      $this->markTestSkipped('bzip2 command not available.');
    }
  }
  */

  function testMakeContribDestination() {
    $this->runMakefileTest('contrib-destination');
  }

  /** @group make.yml */
  function testMakeContribDestinationYaml() {
    $this->runMakefileTest('contrib-destination-yaml');
  }

  function testMakeDefaults() {
    $this->runMakefileTest('defaults');
  }

  /** @group make.yml */
  function testMakeDefaultsYaml() {
    $this->runMakefileTest('defaults-yaml');
  }

  function testMakeFile() {
    $this->runMakefileTest('file');
  }

  function testMakeFileExtract() {
    // Silently skip file extraction test if unzip is not installed.
    exec('which unzip', $output, $whichUnzipErrorCode);
    if (!$whichUnzipErrorCode) {
      $this->runMakefileTest('file-extract');
    }
    else {
      $this->markTestSkipped('unzip command not available.');
    }
  }

  function testMakeGet() {
    $this->runMakefileTest('get');
  }

  function testMakeGit() {
    $this->runMakefileTest('git');
  }

  function testMakeGitSimple() {
    $this->runMakefileTest('git-simple');
  }

  function testMakeGZip() {
    // Silently skip gzip test if either gzip or unzip is not installed.
    exec('which gzip', $output, $whichGzipErrorCode);
    if (!$whichGzipErrorCode) {
      exec('which unzip', $output, $whichUnzipErrorCode);
      if (!$whichUnzipErrorCode) {
        $this->runMakefileTest('gzip');
      }
      else {
        $this->markTestSkipped('unzip command not available.');
      }
    }
    else {
      $this->markTestSkipped('gzip command not available.');
    }
  }

  function testMakeIgnoreChecksums() {
    $this->runMakefileTest('ignore-checksums');
  }

  function testMakeInclude() {
    $this->runMakefileTest('include');
  }

  /** @group make.yml */
  function testMakeIncludeYaml() {
    $this->runMakefileTest('include-yaml');
  }

  /**
   * Test git support on includes directive.
   */
  function testMakeIncludesGit() {
    $config = $this->getMakefile('includes-git');
    $options = array();
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $this->drush('make', array($makefile, UNISH_SANDBOX . '/test-git-includes'), $options);

    // Verify that core and example main module were downloaded.
    $this->assertFileExists(UNISH_SANDBOX . '/test-git-includes/README.txt');
    $this->assertFileExists(UNISH_SANDBOX . '/test-git-includes/sites/all/modules/contrib/apachesolr/README.txt');

    // Verify that module included in sub platform was downloaded.
    $this->assertFileExists(UNISH_SANDBOX . '/test-git-includes/sites/all/modules/contrib/jquery_update/README.txt');
  }

  function testMakeLimitProjects() {
    $this->runMakefileTest('limit-projects');
    $this->runMakefileTest('limit-projects-multiple');
  }

  function testMakeLimitLibraries() {
    $this->runMakefileTest('limit-libraries');
    $this->runMakefileTest('limit-libraries-multiple');
  }


  function testMakeMd5Fail() {
    $this->runMakefileTest('md5-fail');
  }

  function testMakeMd5Succeed() {
    $this->runMakefileTest('md5-succeed');
  }

  /**
   * Test that make_move_build() doesn't wipe out directories that it shouldn't.
   */
  function testMakeMoveBuild() {
    // Manually download a module.
    $options = array(
      'default-major' => 6, // The makefile used below is core = "6.x".
      'destination' => UNISH_SANDBOX . '/sites/all/modules/contrib',
      'yes' => NULL,
      'dev' => NULL,
    );
    $this->drush('pm-download', array('cck_signup'), $options);

    // Build a make file.
    $config = $this->getMakefile('contrib-destination');
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $this->drush('make', array($makefile, '.'), $config['options']);

    // Verify that the manually downloaded module still exists.
    $this->assertFileExists(UNISH_SANDBOX . '/sites/all/modules/contrib/cck_signup/README.txt');
  }

  function testMakeNoPatchTxt() {
    $this->runMakefileTest('no-patch-txt');
  }

  function testMakeNoRecursion() {
    $config = $this->getMakefile('recursion');
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];

    $install_directory = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'norecursion';
    $this->drush('make', array('--no-core', '--no-recursion', $makefile, $install_directory));
    $this->assertNotContains("ctools", $this->getOutput(), "Make with --no-recursion does not recurse into drupal_forum to download ctools.");
  }

  /**
   * Test no-core and working-copy in options array.
   */
  function testMakeOptionsArray() {
    // Use the goptions-array.make file.
    $config = $this->getMakefile('options-array');

    $makefile_path = dirname(__FILE__) . '/makefiles';
    $makefile = $makefile_path . '/' . $config['makefile'];
    $install_directory = UNISH_SANDBOX . '/options-array';
    $this->drush('make', array($makefile, $install_directory));

    // Test cck_signup .git/HEAD file.
    $this->assertFileExists($install_directory . '/sites/all/modules/cck_signup/.git/HEAD');
    $contents = file_get_contents($install_directory . '/sites/all/modules/cck_signup/.git/HEAD');
    $this->assertContains('2fe932c', $contents);

    // Test context_admin .git/HEAD file.
    $this->assertFileExists($install_directory . '/sites/all/modules/context_admin/.git/HEAD');
    $contents = file_get_contents($install_directory . '/sites/all/modules/context_admin/.git/HEAD');
    $this->assertContains('eb9f05e', $contents);
  }

  /**
   * Test per project working-copy option.
   */
  function testMakeOptionsProject() {
    // Use the options-project.make file.
    $config = $this->getMakefile('options-project');

    $makefile_path = dirname(__FILE__) . '/makefiles';
    $options = array('no-core' => NULL);
    $makefile = $makefile_path . '/' . $config['makefile'];
    $install_directory = UNISH_SANDBOX . '/options-project';
    $this->drush('make', array($makefile, $install_directory), $options);

    // Test context_admin .git/HEAD file.
    $this->assertFileExists($install_directory . '/sites/all/modules/context_admin/.git/HEAD');
    $contents = file_get_contents($install_directory . '/sites/all/modules/context_admin/.git/HEAD');
    $this->assertContains('eb9f05e', $contents);

    // Test cck_signup .git/HEAD file does not exist.
    $this->assertFileNotExists($install_directory . '/sites/all/modules/cck_signup/.git/HEAD');

    // Test caption_filter .git/HEAD file.
    $this->assertFileExists($install_directory . '/sites/all/modules/contrib/caption_filter/.git/HEAD');
    $contents = file_get_contents($install_directory . '/sites/all/modules/contrib//caption_filter/.git/HEAD');
    $this->assertContains('c9794cf', $contents);
  }

  function testMakePatch() {
    $this->runMakefileTest('patch');
  }

  function testMakeRecursion() {
    $this->runMakefileTest('recursion');
  }

  function testMakeRecursionOverride() {
    // @todo This is skipped for now since the test relies on sourceforge.
    // It can be replaced if a suitable module that installs projects (not
    // libraries, which aren't properly overridable).
    $this->markTestSkipped('skipping recursion-override test');
    return;

    // Silently skip file extraction test if unzip is not installed.
    exec('which unzip', $output, $whichUnzipErrorCode);
    if (!$whichUnzipErrorCode) {
      $this->runMakefileTest('recursion-override');
    }
    else {
      $this->markTestSkipped('unzip command not available.');
    }
  }

  function testMakeSubtree() {
    // Silently skip subtree test if unzip is not installed.
    exec('which unzip', $output, $whichUnzipErrorCode);
    if (!$whichUnzipErrorCode) {
      $config = $this->getMakefile('subtree');

      $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
      $install_directory = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'subtree';
      $this->drush('make', array('--no-core', $makefile, $install_directory));

      $files['nivo-slider'] = array(
        'exists' => array(
          'jquery.nivo.slider.js',
          'jquery.nivo.slider.pack.js',
          'license.txt',
          'nivo-slider.css',
          'README',
        ),
        'notexists' => array(
          '__MACOSX',
          'nivo-slider',
        ),
      );
      $files['fullcalendar'] = array(
        'exists' => array(
          'fullcalendar.css',
          'fullcalendar.js',
          'fullcalendar.min.js',
          'fullcalendar.print.css',
          'gcal.js',
        ),
        'notexists' => array(
          'changelog.txt',
          'demos',
          'fullcalendar',
          'GPL-LICENSE.txt',
          'jquery',
          'MIT-LICENSE.txt',
        ),
      );
      $basedir = $install_directory . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'all' . DIRECTORY_SEPARATOR . 'libraries';
      foreach ($files as $lib => $details) {
        $dir =  $basedir . DIRECTORY_SEPARATOR . $lib;
        if (!empty($details['exists'])) {
          foreach ($details['exists'] as $file) {
            $this->assertFileExists($dir . DIRECTORY_SEPARATOR . $file);
          }
        }

        if (!empty($details['notexists'])) {
          foreach ($details['notexists'] as $file) {
            $this->assertFileNotExists($dir . DIRECTORY_SEPARATOR . $file);
          }
        }
      }
    }
    else {
      $this->markTestSkipped('unzip command not available.');
    }
  }

  function testMakeSvn() {
    return $this->markTestSkipped('svn support is deprecated.');
    // Silently skip svn test if svn is not installed.
    exec('which svn', $output, $whichSvnErrorCode);
    if (!$whichSvnErrorCode) {
      $this->runMakefileTest('svn');
    }
    else {
      $this->markTestSkipped('svn command not available.');
    }
  }

  /**
   * Translations can change arbitrarily, so these test for the existence of .po
   * files, rather than trying to match a build hash.
   */
  function testMakeTranslations() {
    $config = $this->getMakefile('translations');

    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $install_directory = UNISH_SANDBOX . '/translations';
    $this->drush('make', array($makefile, $install_directory), $config['options']);

    $po_files = array(
      'sites/all/modules/token/translations/pt-br.po',
      'sites/all/modules/token/translations/es.po',
    );

    foreach ($po_files as $po_file) {
      $this->assertFileExists($install_directory . '/' . $po_file);
    }
  }

  /**
   * Translations can change arbitrarily, so these test for the existence of .po
   * files, rather than trying to match a build hash.
   */
  function testMakeTranslationsInside() {
    $config = $this->getMakefile('translations-inside');

    $makefile = $this->makefile_path . '/' . $config['makefile'];
    $install_directory = UNISH_SANDBOX . '/translations-inside';
    $this->drush('make', array($makefile, $install_directory));

    $po_files = array(
      'profiles/default/translations/pt-br.po',
      'profiles/default/translations/es.po',
      'sites/all/modules/token/translations/pt-br.po',
      'sites/all/modules/token/translations/es.po',
      'modules/system/translations/pt-br.po',
      'modules/system/translations/es.po',
    );

    foreach ($po_files as $po_file) {
      $this->assertFileExists($install_directory . '/' . $po_file);
    }
  }

  /**
   * Translations can change arbitrarily, so these test for the existence of .po
   * files, rather than trying to match a build hash.
   */
  function testMakeTranslationsInside7() {
    $config = $this->getMakefile('translations-inside7');

    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $install_directory = UNISH_SANDBOX . '/translations-inside7';
    $this->drush('make', array($makefile, $install_directory));

    $po_files = array(
      'profiles/minimal/translations/pt-br.po',
      'profiles/minimal/translations/es.po',
      'profiles/testing/translations/pt-br.po',
      'profiles/testing/translations/es.po',
      'profiles/standard/translations/pt-br.po',
      'profiles/standard/translations/es.po',
      'sites/all/modules/token/translations/pt-br.po',
      'sites/all/modules/token/translations/es.po',
      'modules/system/translations/pt-br.po',
      'modules/system/translations/es.po',
    );

    foreach ($po_files as $po_file) {
      $this->assertFileExists($install_directory . '/' . $po_file);
    }
  }

  /**
   * Test that a distribution can be used as a "core" project.
   */
  function testMakeUseDistributionAsCore() {
    $this->runMakefileTest('use-distribution-as-core');
  }

  /**
   * Test that files without a core attribute are correctly identified.
   */
  public function testNoCoreMakefileParsing() {
    require_once __DIR__ . '/../commands/make/make.utilities.inc';

    // INI.
    $data = file_get_contents(__DIR__ . '/makefiles/no-core.make');
    $parsed = _make_determine_format($data);
    $this->assertEquals('ini', $parsed['format']);
    $this->assertEquals(42, $parsed['projects']['foo']['version']);

    // YAML.
    $data = file_get_contents(__DIR__ . '/makefiles/no-core.make.yml');
    $parsed = _make_determine_format($data);
    $this->assertEquals('yaml', $parsed['format']);
    $this->assertEquals(42, $parsed['projects']['foo']['version']);
  }

}
