<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of a sql:sanitize plugin.
 */
final class SanitizeCommentsCommands extends DrushCommands implements SanitizePluginInterface
{
    use AutowireTrait;

    public function __construct(
        protected Connection $database,
        protected ModuleHandlerInterface $moduleHandler
    ) {
        parent::__construct();
    }

    /**
     * Sanitize comment names from the DB.
     */
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
    public function sanitize($result, CommandData $commandData): void
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
              ->expression('name', "CONCAT('User', uid)")
              ->expression('mail', "CONCAT('user+', uid, '@example.com')")
              ->fields(['homepage' => 'http://example.com'])
              ->condition('uid', 1, '>=')
              ->execute();
            $this->logger()->success(dt('Comment display names and emails removed.'));
        }
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
    public function messages(&$messages, InputInterface $input): void
    {
        if ($this->applies()) {
            $messages[] = dt('Remove comment display names and emails.');
        }
    }

    protected function applies()
    {
        return $this->moduleHandler->moduleExists('comment');
    }
}
