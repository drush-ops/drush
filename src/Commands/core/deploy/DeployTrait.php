<?php

namespace Drush\Commands\core\deploy;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Update\UpdateRegistry;

trait DeployTrait
{
    /**
     * Get the deploy hook update registry.
     */
    public static function getRegistry(): UpdateRegistry
    {
        return new class (
            \Drupal::getContainer()->getParameter('app.root'),
            \Drupal::getContainer()->getParameter('site.path'),
            \Drupal::service('module_handler')->getModuleList(),
            \Drupal::service('keyvalue'),
            \Drupal::service('theme_handler'),
        ) extends UpdateRegistry {
            public function __construct(
                $root,
                $site_path,
                $module_list,
                KeyValueFactoryInterface $key_value_factory,
                ThemeHandlerInterface $theme_handler,
            ) {
                // Do not call the parent constructor, we set the properties directly.
                // We need a different key value store and set the update type.
                $this->root = $root;
                $this->sitePath = $site_path;
                $this->enabledExtensions = array_merge(array_keys($module_list), array_keys($theme_handler->listInfo()));
                $this->keyValue = $key_value_factory->get('deploy_hook');
                $this->updateType = 'deploy';
            }
        };
    }
}
