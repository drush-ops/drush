<?php

namespace Drupal\webprofiler\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class DecoratorPass.
 */
class DecoratorPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    // Builds a decorator around plugin.manager.mail.
    $definition = $container->findDefinition('plugin.manager.mail');
    $definition->setPublic(FALSE);
    $container->setDefinition('webprofiler.debug.plugin.manager.mail.default', $definition);
    $container->register('plugin.manager.mail', 'Drupal\webprofiler\Mail\MailManagerWrapper')
      ->addArgument(new Reference('container.namespaces'))
      ->addArgument(new Reference('cache.discovery'))
      ->addArgument(new Reference('module_handler'))
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('logger.factory'))
      ->addArgument(new Reference('string_translation'))
      ->addArgument(new Reference('webprofiler.debug.plugin.manager.mail.default'))
      ->addArgument(new Reference('webprofiler.mail'))
      ->setProperty('_serviceId', 'plugin.manager.mail');
  }
}
