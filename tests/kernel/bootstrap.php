<?php

/**
 * @file
 * PHPUnit bootstrap for the kernel suite.
 *
 * Chains Drupal core's test bootstrap so that KernelTestBase and the test
 * namespaces of every module under sut/ (including woot) autoload.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$coreBootstrap = $root . '/sut/core/tests/bootstrap.php';
if (!file_exists($coreBootstrap)) {
    fwrite(STDERR, "sut/core is missing. Run composer install first.\n");
    exit(1);
}

// KernelTestBase reads SIMPLETEST_DB. Derive it from UNISH_DB_URL so one
// variable configures every suite. Kernel tests open a second connection,
// which an in-memory sqlite database cannot share, so use a file instead.
//
// This runs once in the main phpunit process; isolated test processes
// inherit the variable and skip this block, so the cleanup below runs
// only after the whole suite has finished.
if (!getenv('SIMPLETEST_DB') && ($url = getenv('UNISH_DB_URL'))) {
    if (str_starts_with($url, 'sqlite:') && str_contains($url, ':memory:')) {
        $dir = "$root/sut/sites/simpletest";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $url = 'sqlite://localhost/sites/simpletest/kernel.sqlite';
        // KernelTestBase drops each test's tables, but the sqlite driver
        // keeps one file per table prefix. Remove them when the run ends.
        register_shutdown_function(static function () use ($dir): void {
            array_map(unlink(...), glob("$dir/kernel.sqlite*") ?: []);
            @rmdir($dir);
        });
    }
    putenv("SIMPLETEST_DB=$url");
    $_ENV['SIMPLETEST_DB'] = $_SERVER['SIMPLETEST_DB'] = $url;
}

// Core's deprecation handler also reads SYMFONY_DEPRECATIONS_HELPER. Any value
// other than "disabled" (such as CI's "disabled=1") turns project ignores
// on, and Drush has no ignore file to load. The kernel suite handles
// deprecations through the extension parameters in phpunit.kernel.xml.dist.
putenv('SYMFONY_DEPRECATIONS_HELPER=disabled');
$_ENV['SYMFONY_DEPRECATIONS_HELPER'] = $_SERVER['SYMFONY_DEPRECATIONS_HELPER'] = 'disabled';

// Core's bootstrap scans these for contrib test namespaces and fails when
// one is missing; composer install only creates the ones Drupal needs.
foreach (['modules', 'profiles', 'themes'] as $dir) {
    if (!is_dir("$root/sut/$dir")) {
        mkdir("$root/sut/$dir");
    }
}

require $coreBootstrap;
