<?php

$aliases['live'] = array (
  'parent' => '@server.digital-ocean',
  'project-type' => 'live',
  'root' => '/srv/www/couturecostume.com/htdocs',
  'uri' => 'couturecostume.com',
  'path-aliases' => array(
    '%dump-dir' => '/var/sql-dump/',
  ),
  'target-command-specific' => array(
    'sql-sync' => array(
      'disable' => array('stage_file_proxy'),
      'permission' => array(
        'authenticated user' => array(
          'remove' => array('access environment indicator'),
        ),
        'anonymous user' => array(
          'remove' => 'access environment indicator',
        ),
      ),
    ),
  ),
);

$aliases['update'] = array (
  'parent' => '@server.nitrogen',
  'root' => '/srv/www/update.couturecostume.com/htdocs',
  'uri' => 'update.couturecostume.com',
  'target-command-specific' => array(
    'sql-sync' => array(
      'enable' => array('environment_indicator', 'stage_file_proxy'),
      'permission' => array(
        'authenticated user' => array(
          'add' => array('access environment indicator'),
        ),
        'anonymous user' => array(
          'add' => 'access environment indicator',
        ),
      ),
    ),
  ),
);
