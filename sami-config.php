<?php

/**
 * A Sami config file. See https://github.com/FriendsOfPHP/Sami.
 */

use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
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
   ->add('10.x', 'Main branch')
   ;

return new Sami($iterator, array(
  // 'theme'                => 'symfony',
  'versions'             => $versions,
  'title'                => 'Drush API',
  'build_dir'            => __DIR__.'/gh-pages/api/%version%',
  'cache_dir'            => __DIR__.'/.sami-cache/%version%',
  'remote_repository'    => new GitHubRemoteRepository('drush-ops/drush', dirname($dir)),
  'default_opened_level' => 2,
));
