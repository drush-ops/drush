<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Drush\Log\DrushLog;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

class DrushLoggerServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(ContainerBuilder $container): void
    {
        $container->register('logger.drupaltodrush', DrushLog::class)
            ->addArgument(new Reference('logger.log_message_parser'))
            ->addTag('logger');
    }
}
