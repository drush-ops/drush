<?php

namespace Drush\Drupal;

use Drush\Log\LogLevel;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

class DrushServiceModfier implements ServiceModifierInterface
{
    /**
     * @inheritdoc
     */
    public function alter(ContainerBuilder $container) {
        drush_log(dt("service modifier alter"), LogLevel::DEBUG);
        // http://symfony.com/doc/2.7/components/dependency_injection/tags.html#register-the-pass-with-the-container
        $container->register('drush.service.consolecommands', 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass('drush.service.consolecommands', 'console.command'));
        $container->register('drush.service.consolidationcommands', 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass('drush.service.consolidationcommands', 'consolidation.commandhandler'));
    }
}
