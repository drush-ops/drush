<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of how to build a sql-sanitize plugin.
 */
class SanitizeSessionsCommands extends DrushCommands implements SqlSanitizePluginInterface
{

    /**
     * Sanitize sessions from the DB.
     *
     * @hook post-command sql-sanitize
     *
     * @inheritdoc
     */
    public function sanitize($result, CommandData $commandData)
    {
        Database::getConnection()->truncate('sessions')->execute();
        $this->logger()->success(dt('Sessions table truncated.'));
    }

    /**
     * @hook on-event sql-sanitize-confirms
     *
     * @inheritdoc
     */
    public function messages(&$messages, InputInterface $input)
    {
        $messages[] = dt('Truncate sessions table.');
    }
}
