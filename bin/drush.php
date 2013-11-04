<?php

require __DIR__ . '/../vendor/autoload.php';
require(dirname(__FILE__) . '/../includes/bootstrap.inc');

use Symfony\Component\Console\Application;
use Drupal\Drush\Version;
use Drupal\Drush\Command\Core\ArchiveDumpCommand;
use Drupal\Drush\Command\Core\ArchiveRestoreCommand;
use Drupal\Drush\Command\Core\StatusCommand;

$cli = new Application('Drush Command Line Interface', Version::VERSION);
$cli->add(new ArchiveDumpCommand);
$cli->add(new ArchiveRestoreCommand);
$cli->add(new StatusCommand);
$cli->run();
