<?php

/**
 * Drupal VM drush aliases.
 *
 * @see example.aliases.drushrc.php.
 */

$aliases['drupalvm.dev'] = array(
  'uri' => 'drupalvm.dev',
  'root' => '/var/www/drupalvm/drupal/web',
  'remote-host' => 'drupalvm.dev',
  'remote-user' => 'vagrant',
  'ssh-options' => '-o PasswordAuthentication=no -i '  . '/.vagrant.d/insecure_private_key',
  'path-aliases' => array(
    '%drush-script' => '/var/www/drupalvm/drupal/vendor/drush/drush/drush',
  ),
);

$aliases['www.drupalvm.dev'] = array(
  'uri' => 'www.drupalvm.dev',
  'root' => '/var/www/drupalvm/drupal/web',
  'remote-host' => 'www.drupalvm.dev',
  'remote-user' => 'vagrant',
  'ssh-options' => '-o PasswordAuthentication=no -i ' . '/.vagrant.d/insecure_private_key',
  'path-aliases' => array(
    '%drush-script' => '/var/www/drupalvm/drupal/vendor/drush/drush/drush',
  ),
);

