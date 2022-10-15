<?php

include "../vendor/autoload.php";

// We need our Symfony Filesystem Path backport available for tests as well.
// (Copied from drush.php front controller.)
if (!class_exists('\Symfony\Component\Filesystem\Path')) {
    include __DIR__ . "/src-symfony-compatibility/Filesystem/Path.php";
}
