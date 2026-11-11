<?php

// This allows php code to add service providers to Drupal core since Drupal 8.
$GLOBALS['conf']['container_service_providers']['DrushServiceProvider'] = 'Drush\DrServiceProvider';
