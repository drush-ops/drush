<?php
  /**
   * Pantheon drush alias file, to be placed in your ~/.drush directory or the aliases
   * directory of your local Drush home. Once it's in place, clear drush cache:
   *
   * drush cc drush
   *
   * To see all your available aliases:
   *
   * drush sa
   *
   * See http://helpdesk.getpantheon.com/customer/portal/articles/411388 for details.
   */

  $aliases['outlandish-josh.test'] = array(
    'uri' => 'test-outlandish-josh.pantheonsite.io',
    'db-url' => 'mysql://pantheon:pw@dbserver.test.site-id.drush.in:11621/pantheon',
    'db-allows-remote' => TRUE,
    'remote-host' => 'appserver.test.site-id.drush.in',
    'remote-user' => 'test.site-id',
    'ssh-options' => '-p 2222 -o "AddressFamily inet"',
    'path-aliases' => array(
      '%files' => 'code/sites/default/files',
      '%drush-script' => 'drush',
     ),
  );
  $aliases['outlandish-josh.live'] = array(
    'uri' => 'www.outlandishjosh.com',
    'db-url' => 'mysql://pantheon:pw@dbserver.live.site-id.drush.in:10516/pantheon',
    'db-allows-remote' => TRUE,
    'remote-host' => 'appserver.live.site-id.drush.in',
    'remote-user' => 'live.site-id',
    'ssh-options' => '-p 2222 -o "AddressFamily inet"',
    'path-aliases' => array(
      '%files' => 'code/sites/default/files',
      '%drush-script' => 'drush',
     ),
  );
  $aliases['outlandish-josh.dev'] = array(
    'uri' => 'dev-outlandish-josh.pantheonsite.io',
    'db-url' => 'mysql://pantheon:pw@dbserver.dev.site-id.drush.in:21086/pantheon',
    'db-allows-remote' => TRUE,
    'remote-host' => 'appserver.dev.site-id.drush.in',
    'remote-user' => 'dev.site-id',
    'ssh-options' => '-p 2222 -o "AddressFamily inet"',
    'path-aliases' => array(
      '%files' => 'code/sites/default/files',
      '%drush-script' => 'drush',
     ),
  );
