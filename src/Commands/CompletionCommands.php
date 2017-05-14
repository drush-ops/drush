<?php
namespace Drush\Commands;

/*
 * Common completion providers. Use them by adding @complete annotation to your method.
 */
use Drush\Commands\core\SiteCommands;

class CompletionCommands
{

    public static function completeSiteAliases()
    {
        return array('values' => array_keys(SiteCommands::siteAllList()));
    }

    public static function completeModules()
    {
        if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
            $extension_config = \Drupal::configFactory()->getEditable('core.extension');
            $installed_modules = $extension_config->get('module') ?: array();
            return array('values' => array_keys($installed_modules));
        }
    }
}
