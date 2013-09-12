#!/usr/bin/env php
<?php
// app/console

use Drupal\Drush\Command\StatusCommand;
use Symfony\Component\Console\Application;

(@include_once __DIR__ . '/../vendor/autoload.php') || @include_once __DIR__ . '/../../../autoload.php';

$application = new Application();
$application->add(new StatusCommand);
$application->run();
