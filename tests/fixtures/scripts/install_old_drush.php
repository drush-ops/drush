<?php

$old_site = dirname(__DIR__) . '/sites/drupal-with-old-drush';

// TODO: Should we check to see if install is valid before making this a no-op?
if (is_dir($old_site)) {
    return;
}

passthru("git clone git@github.com:drupal-composer/drupal-project.git --branch='8.x' $old_site");

passthru("composer --working-dir=$old_site remove drupal/console --no-update");
passthru("composer --working-dir=$old_site require drush/drush:$drush_version --no-update");

passthru("composer --working-dir=$old_site install");
