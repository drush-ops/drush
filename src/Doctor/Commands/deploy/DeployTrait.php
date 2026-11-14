<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\deploy;

use Drupal\Core\Update\UpdateRegistry;
use Drush\Doctor\Commands\ConsoleTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared by the deploy hook commands that run under dr.
 */
trait DeployTrait
{
    use ConsoleTrait;

    /**
     * The registry of HOOK_deploy_NAME() functions.
     *
     * Core's post update registry, pointed at another function name and
     * key/value collection.
     */
    protected function registry(ContainerInterface $container): UpdateRegistry
    {
        return new UpdateRegistry(
            $container->getParameter('app.root'),
            $container->getParameter('site.path'),
            $container->get('module_handler')->getModuleList(),
            $container->get('keyvalue')->get('deploy_hook'),
            $container->get('theme_handler'),
            $container->get('cache.memory'),
            'deploy',
        );
    }

    /**
     * @return list<array{module: string, hook: string, description: string}>
     */
    protected function pendingHooks(UpdateRegistry $registry): array
    {
        $rows = [];
        foreach ($registry->getPendingUpdateInformation() as $module => $update) {
            foreach ($update['pending'] ?? [] as $hook => $description) {
                $rows[] = ['module' => $module, 'hook' => $hook, 'description' => $description];
            }
        }
        return $rows;
    }

    protected function renderTable(array $rows): string
    {
        return $this->table([
            (string) $this->t('Module'),
            (string) $this->t('Hook'),
            (string) $this->t('Description'),
        ], $rows);
    }
}
