<?php

namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
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
     * @hook post-command sql-sanitize
     */
    public function sanitize($result, CommandData $commandData);

    /**
     * @hook on-event sql-sanitize-confirms
     *
     * @param array $messages An array of messages to show during confirmation.
     * @param InputInterface $input The effective commandline input for this request.
     *
     * @return String[]
     *   An array of messages.
     */
    public function messages(&$messages, InputInterface $input);
}
