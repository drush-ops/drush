<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Drupal\Commands\sql\Exit;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Implement this interface when building a Drush sql-sanitize plugin.
 */
interface SanitizePluginInterface
{
    /**
     * Run your sanitization logic using standard Drupal APIs.
     *
     * @param $result Exit code from the main operation for sql-sanitize.
     * @param CommandData $commandData Information about the current request.
     *
     * Use `#[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]`
     */
    public function sanitize($result, CommandData $commandData);

    /**
     * Use #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
     *
     * @param array $messages An array of messages to show during confirmation. Make changes by reference.
     * @param InputInterface $input The effective commandline input for this request.
     */
    public function messages(array &$messages, InputInterface $input);
}
