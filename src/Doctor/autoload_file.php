<?php

use Drush\Doctor\DoctorServiceProvider;

// This allows php code to add service providers to Drupal core since Drupal 8.
$GLOBALS['conf']['container_service_providers']['DrushServiceProvider'] = DoctorServiceProvider::class;

// Drupal ships OutputFormat from 12.1. This loader is appended, so it is only
// asked when Composer cannot find the enum in core.
spl_autoload_register(static function (string $class): void {
    if ($class === 'Drupal\Core\Command\OutputFormat') {
        require __DIR__ . '/../../src-drupal-compatibility/OutputFormat.php';
    }
});
