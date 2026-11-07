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
if (!getenv('SIMPLETEST_DB') && ($url = getenv('UNISH_DB_URL'))) {
    if (str_starts_with($url, 'sqlite:') && str_contains($url, ':memory:')) {
        $url = 'sqlite://localhost/sites/simpletest/kernel.sqlite';
    }
    putenv("SIMPLETEST_DB=$url");
    $_ENV['SIMPLETEST_DB'] = $_SERVER['SIMPLETEST_DB'] = $url;
}

// Core's bootstrap scans these for contrib test namespaces and fails when
// one is missing; composer install only creates the ones Drupal needs.
foreach (['modules', 'profiles', 'themes'] as $dir) {
    if (!is_dir("$root/sut/$dir")) {
        mkdir("$root/sut/$dir");
    }
}

require $coreBootstrap;
