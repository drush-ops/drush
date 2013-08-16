<?php

/**
 * Make makefile tests.
 * @group make
 * @group slow
 */
class makeMakefileCase extends Drush_CommandTestCase {
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

  function testMakeGet() {
    $this->runMakefileTest('get');
  }

  function testMakeGit() {
    $this->runMakefileTest('git');
  }

  function testMakeGitSimple() {
    $this->runMakefileTest('git-simple');
  }

  function testMakeNoPatchTxt() {
    $this->runMakefileTest('no-patch-txt');
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

  function testMakeInclude() {
    $this->runMakefileTest('include');
  }

  function testMakeRecursion() {
    $this->runMakefileTest('recursion');
  }

  function testMakeRecursionOverride() {
    $this->runMakefileTest('recursion-override');
  }

  function testMakeSvn() {
    // Silently skip svn test if svn is not installed.
    exec('which svn', $output, $whichSvnErrorCode);
    if (!$whichSvnErrorCode) {
      $this->runMakefileTest('svn');
    }
    else {
      $this->markTestSkipped('svn command not available.');
    }
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

  function testMakeContribDestination() {
    $this->runMakefileTest('contrib-destination');
  }

  function testMakeDefaults() {
    $this->runMakefileTest('defaults');
  }

  function testMakeFile() {
    $this->runMakefileTest('file');
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

  function testMakeGZip() {
    // Silently skip gzip test if gzip is not installed.
    exec('which gzip', $output, $whichGzipErrorCode);
    if (!$whichGzipErrorCode) {
      $this->runMakefileTest('gzip');
    }
    else {
      $this->markTestSkipped('gzip command not available.');
    }
  }

  function testMakeSubtree() {
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

  function testMakeMd5Succeed() {
    $this->runMakefileTest('md5-succeed');
  }

  function testMakeMd5Fail() {
    $this->runMakefileTest('md5-fail');
  }

  function testMakeIgnoreChecksums() {
    $this->runMakefileTest('ignore-checksums');
  }

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
    $this->assertContains('; Information added by drush on ' . date('Y-m-d'), $contents);
    $this->assertContains('version = "2fe932c"', $contents);
    $this->assertContains('project = "cck_signup"', $contents);

    // Verify that a reference cache was created.
    $cache_dir = UNISH_CACHE . DIRECTORY_SEPARATOR . 'cache';
    $this->assertFileExists($cache_dir . '/git/cck_signup-' . md5('http://git.drupal.org/project/cck_signup.git'));

    // Test context_admin.info file.
    $this->assertFileExists(UNISH_SANDBOX . '/test-build/sites/all/modules/context_admin/context_admin.info');
    $contents = file_get_contents(UNISH_SANDBOX . '/test-build/sites/all/modules/context_admin/context_admin.info');
    $this->assertContains('; Information added by drush on ' . date('Y-m-d'), $contents);
    $this->assertContains('version = "eb9f05e"', $contents);
    $this->assertContains('project = "context_admin"', $contents);

    // Verify git reference cache exists.
    $this->assertFileExists($cache_dir . '/git/context_admin-' . md5('http://git.drupal.org/project/context_admin.git'));

    // Text caption_filter .info rewrite.
    $this->assertFileExists(UNISH_SANDBOX . '/test-build/sites/all/modules/contrib/caption_filter/caption_filter.info');
    $contents = file_get_contents(UNISH_SANDBOX . '/test-build/sites/all/modules/contrib/caption_filter/caption_filter.info');
    $this->assertContains('; Information added by drush on ' . date('Y-m-d'), $contents);
    $this->assertContains('version = "7.x-1.2+0-dev"', $contents);
    $this->assertContains('project = "caption_filter"', $contents);
  }

  function testMakeFileExtract() {
    $this->runMakefileTest('file-extract');
  }

  function testMakeLimitProjects() {
    $this->runMakefileTest('limit-projects');
    $this->runMakefileTest('limit-projects-multiple');
  }

  function testMakeLimitLibraries() {
    $this->runMakefileTest('limit-libraries');
    $this->runMakefileTest('limit-libraries-multiple');
  }

  /**
   * Test that make_move_build() doesn't wipe out directories that it shouldn't.
   */
  function testMakeMoveBuild() {
    // Manually download a module.
    $this->drush('pm-download', array('cck_signup'), array('destination' => UNISH_SANDBOX . '/sites/all/modules/contrib', 'yes' => NULL));

    // Build a make file.
    $config = $this->getMakefile('contrib-destination');
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $this->drush('make', array($makefile, '.'), $config['options']);

    // Verify that the manually downloaded module still exists.
    $this->assertFileExists(UNISH_SANDBOX . '/sites/all/modules/contrib/cck_signup/README.txt');
  }

  /**
   * Test that a distribution can be used as a "core" project.
   */
  function testMakeUseDistributionAsCore() {
    $this->runMakefileTest('use-distribution-as-core');
  }

  function getMakefile($key) {
    static $tests = array(
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
      'no-patch-txt' => array(
        'name'     => 'Test --no-patch-txt option',
        'makefile' => 'patches.make',
        'build'    => TRUE,
        'md5' => '59267a04f98374ed5b0b75e90cefcd9c',
        'options'  => array('no-core' => NULL, 'no-patch-txt' => NULL),
      ),
      'patch' => array(
        'name'     => 'Test patching and writing of PATCHES.txt file',
        'makefile' => 'patches.make',
        'build'    => TRUE,
        'md5' => 'edf94818907bff754b24ac5c34506028',
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
        'md5' => '4e9883d6f9f6572af287635689c2545d',
        'options'  => array('no-core' => NULL),
      ),
      'defaults' => array(
        'name'     => 'Test defaults array.',
        'makefile' => 'defaults.make',
        'build'    => TRUE,
        'md5' => 'e6c0d6b37cd8573cbd188742b95a274e',
        'options'  => array('no-core' => NULL, 'contrib-destination' => '.'),
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
      'gzip' => array(
        'name'     => 'gzip',
        'makefile' => 'gzip.make',
        'build'    => TRUE,
        'md5'      => '615975484966c36f4c9186601afd61e0',
        'options'  => array('no-core' => NULL),
      ),
      'subtree' => array(
        'name'     => 'Use subtree from downloaded archive',
        'makefile' => 'subtree.make',
        'build'    => TRUE,
        'md5' => 'db3770d8b4c9ce77510cbbcc566da9b8',
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
      'file-extract' => array(
        'name'     => 'Extract archives',
        'makefile' => 'file-extract.make',
        'build'    => TRUE,
        'md5' => 'f92471fb7979e45d2554c61314ac6236',
        // @todo This test often fails with concurrency set to more than one.
        'options'  => array('no-core' => NULL, 'concurrency' => 1),
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
      'use-distribution-as-core' => array(
        'name'     => 'Use distribution as core',
        'makefile' => 'use-distribution-as-core.make',
        'build'    => TRUE,
        'md5' => '643a603025a20d498eb583a1e7970bad',
        'options'  => array(),
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
    );
    return $tests[$key];
  }
}
