<?php

/**
 * A Doctum config file. See https://github.com/code-lts/doctum#readme.
 */

use Doctum\Doctum;
use Doctum\RemoteRepository\GitHubRemoteRepository;
use Doctum\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$dir = __DIR__.'/src';

$iterator = Finder::create()
  ->files()
  ->name('*.php')
  //->exclude('Resources')
  //->exclude('Tests')
  ->in($dir)
;

// Generate documentation for the main branch only
$versions = GitVersionCollection::create($dir)
//   ->addFromTags('10.*') // Also generate documentation for 10.x semver releases
   ->add('11.x', 'Main branch');

return new Doctum($iterator, [
  // 'theme'                => 'symfony',
  'versions'             => $versions,
  'title'                => 'Drush API',
//  'build_dir'            => __DIR__.'/gh-pages/api/%version%',
  'build_dir'            => __DIR__.'/latest/api',
  'cache_dir'            => __DIR__.'/.doctum-cache/%version%',
  'remote_repository'    => new GitHubRemoteRepository('drush-ops/drush', dirname($dir)),
  'default_opened_level' => 2,
]);
