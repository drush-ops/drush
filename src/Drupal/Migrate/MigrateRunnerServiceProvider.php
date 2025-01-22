<?php

namespace Drush\Drupal\Migrate;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Registers a new migrate_prepare_row hook implementation.
 *
 * A new 'migrate_prepare_row' hook implementation in behalf of the system.
 *
 * @todo Deprecate this hook implementation when #2952291 lands.
 * @see https://www.drupal.org/project/drupal/issues/2952291
 */
class MigrateRunnerServiceProvider implements ServiceModifierInterface
{
    /**
     * {@inheritdoc}
     */
    public function alter(ContainerBuilder $container): void
    {
        $modules = $container->hasParameter('container.modules') ? $container->getParameter('container.modules') : [];
        if (!isset($modules['migrate'])) {
            return;
        }

        if (!$container->hasParameter('hook_implementations_map')) {
            return;
        }

        $map = $container->getParameter('hook_implementations_map');
        $hook = 'migrate_prepare_row';
        $class = MigrateRunnerHooks::class;
        $method = 'prepareRow';
        $container->register($class, $class)
            ->addTag('kernel.event_listener', [
                'event' => 'drupal_hook.' . $hook,
                'method' => $method,
                'priority' => 0,
            ])->setAutowired(true);
        $map[$hook][$class][$method] = 'system';
        $container->setParameter('hook_implementations_map', $map);
    }
}
