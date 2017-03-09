<?php

namespace Drush\Drupal;

use Drush\Log\LogLevel;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

class DrushServiceModifier implements ServiceModifierInterface
{
    /**
     * @inheritdoc
     */
    public function alter(ContainerBuilder $container) {
        drush_log(dt("service modifier alter"), LogLevel::DEBUG);
        // http://symfony.com/doc/2.7/components/dependency_injection/tags.html#register-the-pass-with-the-container
        $container->register('drush.service.consolecommands', 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass('drush.service.consolecommands', 'drush.command'));
        $container->register('drush.service.consolidationcommands', 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass('drush.service.consolidationcommands', 'consolidation.commandhandler'));
    }
  /**
   * Checks existing service definitions for the presence of modification.
   *
   * @param $container_definition
   *   Cached container definition
   * @return bool
   */
    public function check($container_definition) {
      return isset($container_definition['services']['drush.service.consolecommands']) &&
        isset($container_definition['services']['drush.service.consolidationcommands']);
    }
}
