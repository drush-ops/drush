<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands;

use Drupal\Core\Command\DrupalConsoleLogger;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared by the updatedb commands that run under dr.
 */
trait UpdatedbTrait
{
    use StringTranslationTrait;

    /**
     * Loads the update API and the .install files of installed modules.
     *
     * The same three steps as DbUpdateController::handle().
     */
    protected function loadUpdateFiles(): void
    {
        $root = $this->kernel->getAppRoot();
        require_once $root . '/core/includes/install.inc';
        require_once $root . '/core/includes/update.inc';
        drupal_load_updates();
    }

    /**
     * Returns the pending updates, modelled on DbUpdateController::selection().
     *
     * @return array{rows: list<array{extension: string, update_id: int|string, type: string, description: string}>, start: array<string, int>}
     *   The pending updates as table rows, and the first hook_update_N to run
     *   per module, which update_resolve_dependencies() takes as input.
     */
    protected function pendingUpdates(ContainerInterface $container, LoggerInterface $logger): array
    {
        $rows = [];
        $start = [];
        $lists = [
            'update' => update_get_update_list(),
            'post_update' => $container->get('update.post_update_registry')->getPendingUpdateInformation(),
        ];
        foreach ($lists as $type => $list) {
            foreach ($list as $extension => $update) {
                if (!isset($update['start'])) {
                    $logger->warning('@warning', ['@warning' => $this->plainText($update['warning'])]);
                    continue;
                }
                if ($type === 'update') {
                    $start[$extension] = $update['start'];
                }
                foreach ($update['pending'] ?? [] as $id => $description) {
                    if ($type === 'update') {
                        // Core formats these as "$id - $description".
                        $description = preg_replace('/^' . $id . ' - /', '', $description);
                    }
                    $rows[$extension . '_' . $type . '_' . $id] = [
                        'extension' => $extension,
                        'update_id' => $id,
                        'type' => $type,
                        'description' => trim((string) $description),
                    ];
                }
            }
        }

        // Label hook_update_N implementations whose dependencies cannot be met.
        $blocked = false;
        foreach ($start ? update_resolve_dependencies($start) : [] as $data) {
            $key = $data['module'] . '_update_' . $data['number'];
            if (!$data['allowed'] && isset($rows[$key])) {
                $blocked = true;
                $rows[$key]['description'] .= ' ' . sprintf('Cannot be applied: depends on %s.', implode(', ', $data['missing_dependencies']));
            }
        }
        if ($blocked) {
            $logger->warning('Some of the pending updates cannot be applied because their dependencies were not met.');
        }

        return ['rows' => array_values($rows), 'start' => $start];
    }

    protected function renderTable(array $rows): string
    {
        $buffer = new BufferedOutput();
        (new Table($buffer))
            ->setHeaders([
                (string) $this->t('Extension'),
                (string) $this->t('Update ID'),
                (string) $this->t('Type'),
                (string) $this->t('Description'),
            ])
            ->setRows($rows)
            ->render();
        return $buffer->fetch();
    }

    /**
     * The logger channel core's update system reports on.
     */
    protected function updateLogger(ContainerInterface $container): LoggerInterface
    {
        return $container->get('logger.factory')->get('update');
    }

    /**
     * Sends Drupal log messages to the console.
     *
     * dr does this once per command through ConsoleSubscriber, but a container
     * rebuild replaces logger.console with an instance that has no console
     * logger attached, so it is done again after every rebuild. It also lets
     * kernel tests see the log output.
     */
    protected function attachConsoleLogger(ContainerInterface $container, OutputInterface $output): void
    {
        if (!$container->has('logger.console')) {
            return;
        }
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $container->get('logger.console')->setLogger(new ConsoleLogger($errorOutput, DrupalConsoleLogger::verbosityLevelMap()));
    }

    /**
     * Renders markup, a render array or a string as plain console text.
     */
    protected function plainText(mixed $value, ?ContainerInterface $container = null): string
    {
        if (is_array($value)) {
            $value = $container?->get('renderer')->renderInIsolation($value) ?? '';
        }
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }
}
