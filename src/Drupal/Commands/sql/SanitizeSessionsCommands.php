<?php
namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of how to build a sql-sanitize plugin.
 */
class SanitizeSessionsCommands extends DrushCommands implements SanitizePluginInterface
{
    protected $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }


    /**
     * Sanitize sessions from the DB.
     *
     * @hook post-command sql-sanitize
     *
     * @inheritdoc
     */
    public function sanitize($result, CommandData $commandData)
    {
        $this->getDatabase()->truncate('sessions')->execute();
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
