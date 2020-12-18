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
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
          'migrate.post_row_save' => ['updateProgressBar', -10],
          'migrate.map_delete' => ['updateProgressBar', -10],
          'migrate.post_import' => ['finishProgress', 10],
          'migrate.post_rollback' => ['finishProgress', 10],
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
        $this->progressBar = new ProgressBar($output, $source->count(), 0);
        $this->output = $output;
    }

    /**
     * Event callback for removing the progress bar after operation is finished.
     */
    public function finishProgress(): void
    {
        if ($this->progressBar) {
            $this->progressBar->finish();
            $this->output->write("\n");
            $this->progressBar->clear();
        }
    }
}
