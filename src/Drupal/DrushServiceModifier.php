<?php

namespace Drush\Drupal;

use DrupalCodeGenerator\Application;
use Drush\Drush;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

class DrushServiceModifier implements ServiceModifierInterface
{
    // Holds list of command classes implementing Symfony\Console\Component\Command
    const DRUSH_CONSOLE_SERVICES = 'drush.console.services';
    // Holds list of command classes implemented with annotated commands
    const DRUSH_COMMAND_SERVICES = 'drush.command.services';
    // Holds list of command info alterer classes.
    const DRUSH_COMMAND_INFO_ALTERER_SERVICES = 'drush.command_info_alterer.services';
    // Holds list of classes implementing Drupal Code Generator classes
    const DRUSH_GENERATOR_SERVICES = 'drush.generator.services';

    /**
     * @inheritdoc
     */
    public function alter(ContainerBuilder $container): void
    {
        Drush::logger()->debug(dt("Service modifier alter."));
        // http://symfony.com/doc/2.7/components/dependency_injection/tags.html#register-the-pass-with-the-container
        $container->register(self::DRUSH_CONSOLE_SERVICES, 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass(self::DRUSH_CONSOLE_SERVICES, 'console.command'));
        $container->register(self::DRUSH_COMMAND_SERVICES, 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass(self::DRUSH_COMMAND_SERVICES, 'drush.command'));
        $container->register(self::DRUSH_COMMAND_INFO_ALTERER_SERVICES, 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass(self::DRUSH_COMMAND_INFO_ALTERER_SERVICES, 'drush.command_info_alterer'));
        $container->register(self::DRUSH_GENERATOR_SERVICES, 'Drush\Command\ServiceCommandlist');
        $container->addCompilerPass(new FindCommandsCompilerPass(self::DRUSH_GENERATOR_SERVICES, 'drush.generator.v' . Application::API));
    }

    /**
     * Checks existing service definitions for the presence of modification.
     *
     * @param $container_definition
     *   Cached container definition
     */
    public function check($container_definition): bool
    {
        return
            isset($container_definition['services'][self::DRUSH_CONSOLE_SERVICES]) &&
            isset($container_definition['services'][self::DRUSH_COMMAND_SERVICES]) &&
            isset($container_definition['services'][self::DRUSH_COMMAND_INFO_ALTERER_SERVICES]) &&
            isset($container_definition['services'][self::DRUSH_GENERATOR_SERVICES]);
    }
}
