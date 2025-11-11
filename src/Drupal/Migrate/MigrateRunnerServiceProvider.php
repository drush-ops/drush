<?php

namespace Drush\Drupal\Migrate;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

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

        $hook = 'migrate_prepare_row';
        $class = MigrateRunnerHooks::class;
        $method = 'prepareRow';

        // Remove deprecation layer when support for Drupal <11.3 ends.
        // @see https://www.drupal.org/node/3550627
        DeprecationHelper::backwardsCompatibleCall(
            currentVersion: \Drupal::VERSION,
            deprecatedVersion: '11.3.0',
            currentCallable: function () use ($container, $class, $hook, $method): void {
                if (!$container->hasParameter('.hook_data')) {
                    return;
                }

                $map = $container->getParameter('.hook_data');
                $identifier = "$class:$method";
                $map['hook_list'][$hook] = [$identifier => 'system'] + ($map['hook_list'][$hook] ?? []);
                $container->register($class, $class)->setAutowired(true);
                $container->setParameter('.hook_data', $map);
            },
            deprecatedCallable: function () use ($container, $class, $hook, $method): void {
                if (!$container->hasParameter('hook_implementations_map')) {
                    return;
                }

                $map = $container->getParameter('hook_implementations_map');
                $container->register($class, $class)
                    ->addTag('kernel.event_listener', [
                        'event' => 'drupal_hook.' . $hook,
                        'method' => $method,
                        'priority' => 0,
                    ])->setAutowired(true);
                $map[$hook][$class][$method] = 'system';
                $container->setParameter('hook_implementations_map', $map);
            }
        );
    }
}
