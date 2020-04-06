<?php

namespace Drush\Drupal;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drush\Log\LoggerDrupalToDrush;
use Symfony\Component\DependencyInjection\Reference;

class DrushLoggerServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(ContainerBuilder $container)
    {
        $container->register('logger.drupaltodrush', \Drush\Log\DrushLog::class)
            ->addArgument(new Reference('logger.log_message_parser'))
            ->addTag('logger');
    }
}
