<?php

/**
 * A Doctum config file. See https://github.com/code-lts/doctum#readme.
 */

use Doctum\Doctum;
use Doctum\RemoteRepository\GitHubRemoteRepository;
use Symfony\Component\Finder\Finder;

$dir = __DIR__ . '/src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($dir);

return new Doctum($iterator, [
    'title' => 'Drush API 14',
    'build_dir' => __DIR__ . '/gh-pages/api',
    'cache_dir' => __DIR__ . '/.doctum-cache/%version%',
    'remote_repository' => new GitHubRemoteRepository('drush-ops/drush', dirname($dir)),
    'default_opened_level' => 2,
]);
