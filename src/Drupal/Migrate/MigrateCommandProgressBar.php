<?php

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Import and rollback progress bar.
 */
class MigrateCommandProgressBar implements EventSubscriberInterface
{

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $progressBar;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
          'migrate.post_row_save' => ['updateProgressBar', -10],
          'migrate.map_delete' => ['updateProgressBar', -10],
          'migrate.post_import' => ['clearProgress', 10],
          'migrate.post_rollback' => ['clearProgress', 10],
        ];
    }

    /**
     * Event callback for advancing the progress bar.
     */
    public function updateProgressBar(): void
    {
        if ($this->progressBar) {
            $this->progressBar->advance();
        }
    }

    /**
     * Initializes the progress bar.
     *
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output.
     */
    public function initProgressBar(MigrationInterface $migration, OutputInterface $output): void
    {
        // Clone so that any generators aren't initialized prematurely.
        $source = clone $migration->getSourcePlugin();
        $this->progressBar = new ProgressBar($output, $source->count());
    }

    /**
     * Event callback for removing the progress bar after operation is finished.
     */
    public function clearProgress(): void
    {
        if ($this->progressBar) {
            $this->progressBar->clear();
        }
    }
}
