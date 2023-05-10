<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Composer\Semver\Semver;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Site\Settings;
use Drush\Drush;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common functionality for overridden kernels.
 */
trait DrupalKernelTrait
{
    /** @var ServiceModifierInterface[] */
    protected $serviceModifiers = [];
    protected $serviceFinder;

    /**
     * Add a service modifier to the container builder.
     *
     * The container is not compiled until $kernel->boot(), so there is a chance
     * for clients to add compiler passes et. al. before then.
     */
    public function addServiceModifier(ServiceModifierInterface $serviceModifier)
    {
        Drush::logger()->debug((dt("Add service modifier")));
        $this->serviceModifiers[] = $serviceModifier;
    }

    /**
     * @inheritdoc
     */
    protected function getContainerBuilder()
    {
        Drush::logger()->debug(dt("Get container builder"));
        $container = parent::getContainerBuilder();
        foreach ($this->serviceModifiers as $serviceModifier) {
            $serviceModifier->alter($container);
        }
        return $container;
    }

    /**
     * Initializes the service container.
     *
     * @return ContainerInterface
     */
    protected function initializeContainer()
    {
        $container_definition = $this->getCachedContainerDefinition();

        if ($this->shouldDrushInvalidateContainer()) {
            // Normally when the container is being rebuilt, the existing
            // container is still available for use until the newly built one
            // replaces it. Certain contrib modules rely on services (like State
            // or the config factory) being available for things like defining
            // event subscriptions.
            // @see https://github.com/drush-ops/drush/issues/3123
            if (isset($container_definition)) {
                $class = Settings::get('container_base_class', '\Drupal\Core\DependencyInjection\Container');
                $container = new $class($container_definition);
                $this->attachSynthetic($container);
                \Drupal::setContainer($container);
            }

            $this->invalidateContainer();
        }
        return parent::initializeContainer();
    }

    protected function shouldDrushInvalidateContainer()
    {
        if (empty($this->moduleList) && !$this->containerNeedsRebuild) {
            $container_definition = $this->getCachedContainerDefinition();
            foreach ($this->serviceModifiers as $serviceModifier) {
                if (!$serviceModifier->check($container_definition)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function discoverServiceProviders()
    {
        // Let Drupal discover all of its service providers
        parent::discoverServiceProviders();
    }
}
