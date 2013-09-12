<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Drupal\Drush\Version;
use Drupal\Drush\Command\Core\ArchiveDumpCommand;
use Drupal\Drush\Command\Core\ArchiveRestoreCommand;

$cli = new Application('Drush Command Line Interface', Version::VERSION);
$cli->add(new ArchiveDumpCommand);
$cli->add(new ArchiveRestoreCommand);
$cli->run();
