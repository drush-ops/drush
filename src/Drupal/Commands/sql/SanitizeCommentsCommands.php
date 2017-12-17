<?php
namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of a sql-sanitize plugin.
 */
class SanitizeCommentsCommands extends DrushCommands implements SanitizePluginInterface
{
    protected $database;
    protected $moduleHandler;

    /**
     * SanitizeCommentsCommands constructor.
     * @param $database
     * @param $moduleHandler
     */
    public function __construct($database, $moduleHandler)
    {
        $this->database = $database;
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * Sanitize comment names from the DB.
     *
     * @hook post-command sql-sanitize
     *
     * @inheritdoc
     */
    public function sanitize($result, CommandData $commandData)
    {
        if ($this->applies()) {
            //Update anon.
            $this->database->update('comment_field_data')
            ->fields([
              'name' => 'Anonymous',
              'mail' => '',
              'homepage' => 'http://example.com'
            ])
              ->condition('uid', 0)
              ->execute();

            // Update auth.
            $this->database->update('comment_field_data')
              ->expression('name', "CONCAT('User', `uid`)")
              ->expression('mail', "CONCAT('user+', `uid`, '@example.com')")
              ->fields(['homepage' => 'http://example.com'])
              ->condition('uid', 1, '>=')
              ->execute();
            $this->logger()->success(dt('Comment display names and emails removed.'));
        }
    }

    /**
     * @hook on-event sql-sanitize-confirms
     *
     * @inheritdoc
     */
    public function messages(&$messages, InputInterface $input)
    {
        if ($this->applies()) {
            $messages[] = dt('Remove comment display names and emails.');
        }
    }

    protected function applies()
    {
        Drush::bootstrapManager()->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
        return $this->moduleHandler->moduleExists('comment');
    }
}
