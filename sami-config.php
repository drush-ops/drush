<?php

/**
 * A Sami config file. See https://github.com/FriendsOfPHP/Sami.
 */

use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
  ->files()
  ->name('*.php')
  //->exclude('Resources')
  //->exclude('Tests')
  ->in($dir = __DIR__.'/src')
;

// generate documentation for all v2.0.* tags, the 2.0 branch, and the master one
$versions = GitVersionCollection::create($dir)
  // ->addFromTags('8.*')
 //  ->add('8.x', '8.x branch')
  ->add('master', 'Master branch')
;

return new Sami($iterator, array(
  // 'theme'                => 'symfony',
  'versions'             => $versions,
  'title'                => 'Drush API',
  'build_dir'            => __DIR__.'/api/%version%',
  'cache_dir'            => __DIR__.'/.sami-cache/%version%',
  'remote_repository'    => new GitHubRemoteRepository('drush-ops/drush', dirname($dir)),
  'default_opened_level' => 2,
));