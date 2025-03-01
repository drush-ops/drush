#!/usr/bin/env php
<?php

use Drush\Drush;
use Drush\Config\Environment;
use Drush\Preflight\Preflight;
use Drush\Runtime\Runtime;
use Drush\Runtime\DependencyInjection;
use Symfony\Component\Filesystem\Path;

/**
 * This script runs Drush.
 *
 * ## Responsibilities of this script ##
 *
 *   - Include the Composer autoload file.
 *   - Set up the environment (record user home directory, cwd, etc.).
 *   - Call the Preflight object to do all necessary setup and execution.
 *   - Exit with status code returned
 *
 * It is our goal to put all $_SERVER access and other constructs that are
 * difficult to test in this script to reduce the burden on the unit tests.
 * This script will only be tested via the functional tests.
 *
 * The Drush bootstrap goes through the following steps:
 *
 *   - (ArgsPreprocessor) Preprocess the commandline arguments, considering only:
 *     - The named alias `@sitealias` (removed from arguments if present)
 *     - The --root option (read and retained)
 *     - The --config option (read and retained)
 *     - The --alias-path option (read and retained)
 *   - Load the Drush configuration and alias files from the standard
 *     global locations (including --config and --alias-path)
 *   - Determine the local Drupal site targeted, if any
 *   - Include the Composer autoload for Drupal (if different)
 *   - Extend configuration and alias files to include files in target Drupal site.
 *   - Create the Robo DI container and Symfony Application et. al.
 *   - Run the Symfony Application
 *     - Predispatch: call a remote Drush command if applicable
 *     - Bootstrap Drupal via @bootstrap command hook
 *     - Run commands and command hooks via annotated commands library
 *     - Catch 'command not found' exception, bootstrap Drupal and run again
 *   - Return status code
 *
 * ## Viable Drush configurations ##
 *
 * As of Drush 12, only a site-local Drush will bootstrap Drupal.
 * A globally installed Drush is no longer supported.
 *
 * The following directory layouts are supported:
 *
 * Drush binary in site-local configuration in recommended Drupal site: (typical)
 *
 *         drupal
 *         ├── web
 *         │   ├── core
 *         │   └── index.php
 *         └── vendor
 *             ├── autoload.php
 *   [*1]      ├── bin
 *             │   └── drush.php -> ../drush/drush/drush.php
 *   [*2]      └── drush
 *                 └── drush
 *                     └── drush.php
 *
 * Drush binary in site-local configuration in a legacy Drupal site: (unusual)
 *
 *         drupal
 *         ├── core
 *         ├── index.php
 *         └── vendor
 *             ├── autoload.php
 *   [*1]      ├── bin
 *             │   └── drush.php -> ../drush/drush/drush.php
 *   [*2]      └── drush
 *                 └── drush
 *                     └── drush.php
 *
 * Drush project: (Only used when developing Drush)
 *
 *   [*3]    drush
 *           ├── drush.php
 *           ├── sut
 *           │   ├── core
 *           │   └── index.php
 *           └── vendor
 *               └── autoload.php
 *
 * The possible locations that __DIR__ may point to in the supported
 * configurations are indicated by [*1], [*2] and [*3] in the diagrams
 * above.
 *
 * Note that in the case of the Drush project, the Drupal site used for
 * testing during development, called the "System Under Test", or "sut",
 * counts as a site-local configuration, since the `vendor` directory is
 * common between Drush and Drupal. Drush uses information from the
 * project root to find the Drupal root, so it does not matter what the
 * 'web' directory is called.
 */

// We use PWD if available because getcwd() resolves symlinks, which  could take
// us outside of the Drupal root, making it impossible to find. In addition,
// is_dir() is used as the provided path may not be recognizable by PHP. For
// instance, Cygwin adds a '/cygdrive' prefix to the path which is a virtual
// directory.
$cwd = isset($_SERVER['PWD']) && is_dir($_SERVER['PWD']) ? $_SERVER['PWD'] : getcwd();

$autoloadFile = FALSE;
// Set up autoloader
$candidates = [
    $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php', // https://getcomposer.org/doc/articles/vendor-binaries.md#finding-the-composer-autoloader-from-a-binary
    dirname(__DIR__, 2) . '/autoload.php', // Needed for \Drush\TestTraits\DrushTestTrait::getPathToDrush
    __DIR__ . '/vendor/autoload.php', // For development of Drush itself.
];
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $autoloadFile = $candidate;
        break;
    }
}
if (!$autoloadFile) {
    throw new \Exception("Could not locate autoload.php. cwd is $cwd; __DIR__ is " . __DIR__);
}
$loader = include $autoloadFile;
if (!$loader) {
    throw new \Exception("Invalid autoloadfile: $autoloadFile. cwd is $cwd; __DIR__ is " . __DIR__);
}

// Set up environment
$environment = new Environment(Path::getHomeDirectory(), $cwd, $autoloadFile);
$environment->setConfigFileVariant(Drush::getMajorVersion());
$environment->setLoader($loader);
$environment->applyEnvironment();

// Preflight and run
$preflight = new Preflight($environment);
$di = new DependencyInjection();
$di->desiredHandlers(['errorHandler', 'shutdownHandler']);
$runtime = new Runtime($preflight, $di);
$status_code = $runtime->run($_SERVER['argv']);

exit($status_code);
