<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Drupal\Component\Utility\Timer;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateExecutable as MigrateExecutableBase;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drush\Drupal\Migrate\MigrateEvents as MigrateRunnerEvents;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MigrateExecutable extends MigrateExecutableBase
{
    /**
     * The Symfony console output.
     */
    protected OutputInterface $output;

    /**
     * Counters of map statuses.
     */
    protected array $saveCounters = [
        MigrateIdMapInterface::STATUS_FAILED => 0,
        MigrateIdMapInterface::STATUS_IGNORED => 0,
        MigrateIdMapInterface::STATUS_IMPORTED => 0,
        MigrateIdMapInterface::STATUS_NEEDS_UPDATE => 0,
    ];

    /**
     * Counter of map deletions.
     */
    protected int $deleteCounter = 0;

    /**
     * Maximum number of items to process in this migration.
     */
    protected ?string $limit;

    /**
     * Frequency (in items) at which progress messages should be emitted.
     */
    protected ?int $feedback;

    /**
     * Show timestamp in progress message.
     */
    protected bool $showTimestamp;

    /**
     * Show internal counter in progress message.
     */
    protected bool $showTotal;

    /**
     * List of specific source IDs to import.
     */
    protected array $idlist;

    /**
     * List of all source IDs that are found in source during this migration.
     */
    protected array $allSourceIdValues = [];

    /**
     * Count of number of items processed so far in this migration.
     */
    protected int $counter = 0;

    /**
     * Whether the destination item exists before saving.
     */
    protected bool $preExistingItem = false;

    /**
     * List of event listeners we have registered.
     *
     * @var callable[]
     */
    protected array $listeners = [];

    /**
     * Whether to delete rows missing from source after an import.
     */
    protected bool $deleteMissingSourceRows;

    /**
     * Static cached ID map.
     */
    protected ?MigrateIdMapFilter $idMap;

    /**
     * If the execution exposes a progress bar.
     */
    protected bool $exposeProgressBar;

    /**
     * The Symfony progress bar.
     */
    protected ?ProgressBar $progressBar;

    /**
     * Constructs a new migrate executable instance.
     */
    public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, OutputInterface $output, array $options = [])
    {
        Timer::start('migrate:' . $migration->getPluginId());

        // Provide sane defaults.
        $options += [
            'idlist' => null,
            'limit' => null,
            'feedback' => null,
            'timestamp' => false,
            'total' => false,
            'delete' => false,
            'progress' => true,
        ];

        $this->idlist = MigrateUtils::parseIdList($options['idlist']);

        parent::__construct($migration, $message);

        $this->output = $output;
        $this->limit = $options['limit'];
        $this->feedback = $options['feedback'] ? intval($options['feedback']) : null;
        $this->showTimestamp = $options['timestamp'];
        $this->showTotal = $options['total'];
        // Deleting the missing source rows is not compatible with options that
        // limit number of source rows that will be processed. It should be
        // ignored when:
        // - `--idlist` option is used,
        // - `--limit` option is used,
        // - The migration source plugin has high_water_property set.
        $this->deleteMissingSourceRows = $options['delete'] && !($this->limit || $this->idlist !== [] || !empty($migration->getSourceConfiguration()['high_water_property']));
        // Cannot use the progress bar when:
        // - `--no-progress` option is used,
        // - `--feedback` option is used,
        // - The migration source plugin is configured to skip count.
        $this->exposeProgressBar = $options['progress'] && !$this->feedback && empty($migration->getSourceConfiguration()['skip_count']);

        $this->listeners[MigrateEvents::MAP_SAVE] = [$this, 'onMapSave'];
        $this->listeners[MigrateEvents::PRE_IMPORT] = [$this, 'onPreImport'];
        $this->listeners[MigrateEvents::POST_IMPORT] = [$this, 'onPostImport'];
        $this->listeners[MigrateEvents::MAP_DELETE] = [$this, 'onMapDelete'];
        $this->listeners[MigrateEvents::PRE_ROLLBACK] = [$this, 'onPreRollback'];
        $this->listeners[MigrateEvents::POST_ROLLBACK] = [$this, 'onPostRollback'];
        $this->listeners[MigrateEvents::PRE_ROW_SAVE] = [$this, 'onPreRowSave'];
        $this->listeners[MigrateEvents::POST_ROW_SAVE] = [$this, 'onPostRowSave'];
        $this->listeners[MigrateEvents::POST_ROW_DELETE] = [$this, 'onPostRowDelete'];
        $this->listeners[MigrateRunnerEvents::DRUSH_MIGRATE_PREPARE_ROW] = [$this, 'onPrepareRow'];
        $this->listeners[MigrateMissingSourceRowsEvent::class] = [$this, 'onMissingSourceRows'];

        $eventDispatcher = $this->getEventDispatcher();
        assert($eventDispatcher instanceof EventDispatcherInterface);
        foreach ($this->listeners as $event => $listener) {
            $eventDispatcher->addListener($event, $listener);
        }
    }

    /**
     * Counts up any map save events.
     *
     * @param MigrateMapSaveEvent $event
     *   The map event.
     */
    public function onMapSave(MigrateMapSaveEvent $event): void
    {
        // Only count saves for this migration.
        if ($event->getMap()->getQualifiedMapTableName() == $this->migration->getIdMap()->getQualifiedMapTableName()) {
            $fields = $event->getFields();
            // Distinguish between creation and update.
            if ($fields['source_row_status'] == MigrateIdMapInterface::STATUS_IMPORTED && $this->preExistingItem) {
                $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE]++;
            } else {
                $this->saveCounters[$fields['source_row_status']]++;
            }
        }
    }

    /**
     * Counts up any rollback events.
     *
     * @param MigrateMapDeleteEvent $event
     *   The map event.
     */
    public function onMapDelete(MigrateMapDeleteEvent $event): void
    {
        $this->deleteCounter++;
        $this->updateProgressBar();
    }

    /**
     * Reacts when the import is about to start.
     *
     * @param MigrateImportEvent $event
     *   The import event.
     */
    public function onPreImport(MigrateImportEvent $event): void
    {
        $migration = $event->getMigration();
        $this->initProgressBar($migration);
    }

    /**
     * Handles missing source rows after import.
     *
     * Detect if, before importing, the destination contains rows that are no
     * more available in the source. If we can build such a list, we dispatch
     * the \Drush\Drupal\Migrate\MigrateMissingSourceRowsEvent event, allowing
     * subscribers to perform specific actions on detected destination objects.
     * We also provide a default listener to this event that rolls-back the
     * items, if the `--delete` option has been passed.
     *
     * Custom subscribers, provided by third-party code, may also subscribe,
     * with a higher priority, to the same event, and perform different tasks,
     * such as unpublishing the destination entity and then stopping the event
     * propagation, thus avoiding the destination object rollback, even when
     * the`--delete` option has been passed.
     *
     *
     * @see \Drush\Drupal\Migrate\MigrateExecutable::onMissingSourceRows()
     */
    protected function handleMissingSourceRows(MigrationInterface $migration): void
    {
        $idMap = $migration->getIdMap();
        $idMap->rewind();

        // Collect the destination IDs no more present in source.
        $destinationIds = [];
        while ($idMap->valid()) {
            $mapSourceId = $idMap->currentSource();
            if (!in_array($mapSourceId, $this->allSourceIdValues)) {
                $destinationIds[] = $idMap->currentDestination();
            }
            $idMap->next();
        }

        if ($destinationIds) {
            $missingSourceEvent = new MigrateMissingSourceRowsEvent($migration, $destinationIds);
            $this->getEventDispatcher()->dispatch($missingSourceEvent);
        }
    }

    /**
     * Reacts on detecting a list of missing source rows after an import.
     *
     * Note that third-party code may subscribe to the same event, with a higher
     * priority, and perform different tasks, such as unpublishing the
     * destination entity and then stopping the event propagation, thus avoiding
     * the destination object deletion, even the `--delete` option was passed.
     *
     * @param MigrateMissingSourceRowsEvent $event
     *   The event object.
     */
    public function onMissingSourceRows(MigrateMissingSourceRowsEvent $event): void
    {
        if ($this->deleteMissingSourceRows) {
            $count = count($event->getDestinationIds());
            $this->message->display(
                \Drupal::translation()->formatPlural(
                    $count,
                    '1 item is missing from source and will be rolled back',
                    '@count items are missing from source and will be rolled back'
                )
            );
            // Filter the map on destination IDs.
            $this->idMap = new MigrateIdMapFilter(parent::getIdMap(), [], $event->getDestinationIds());

            $status = $this->migration->getStatus();
            $this->migration->setStatus(MigrationInterface::STATUS_IDLE);
            $this->rollback();
            $this->migration->setStatus($status);
            // Reset the ID map filter.
            $this->idMap = null;
        }
    }

    /**
     * Reacts to migration completion.
     *
     * @param MigrateImportEvent $event
     *   The map event.
     */
    public function onPostImport(MigrateImportEvent $event): void
    {
        $migrateLastImportedStore = \Drupal::keyValue('migrate_last_imported');
        $migrateLastImportedStore->set($event->getMigration()->id(), round(microtime(true) * 1000));
        $this->progressFinish();
        $this->importFeedbackMessage();
        $this->handleMissingSourceRows($event->getMigration());
        $this->unregisterListeners();
    }

    /**
     * Emits information on the import progress.
     */
    protected function importFeedbackMessage(bool $done = true): void
    {
        $processed = $this->getProcessedCount();
        $timer = Timer::read('migrate:' . $this->migration->getPluginId());
        $perMinute = round(60 * ($processed / ($timer / 1000)), 1);
        if ($this->showTimestamp) {
            // Show timestamp in progress message
            $message = '@time -- ';
        } else {
            $message = '';
        }
        $message .= '(@created created, @updated updated, @failures failed, @ignored ignored';
        if ($this->showTotal) {
            $message .= ", @total total processed items";
        }
        $message .= ") in @second seconds (@perminute/min)";
        if ($done) {
            $message .= " - done with '@name'";
        } else {
            $message .= " - continuing with '@name'";
        }
        $singularMessage = "Processed 1 item $message";
        $pluralMessage = "Processed @numitems items $message";
        $this->message->display(
            \Drupal::translation()->formatPlural(
                $processed,
                $singularMessage,
                $pluralMessage,
                [
                    '@time' => \Drupal::service('date.formatter')->format(time(), 'custom', 'r'),
                    '@numitems' => $processed,
                    '@created' => $this->getCreatedCount(),
                    '@updated' => $this->getUpdatedCount(),
                    '@failures' => $this->getFailedCount(),
                    '@ignored' => $this->getIgnoredCount(),
                    '@total' => $this->counter,
                    '@second' => round($timer / 1000, 1),
                    '@perminute' => $perMinute,
                    '@name' => $this->migration->id(),
                ]
            )
        );
        Timer::start('migrate:' . $this->migration->getPluginId());
    }

    /**
     * Reacts when the rollback is about to starts.
     *
     * @param MigrateRollbackEvent $event
     *   The map event.
     */
    public function onPreRollback(MigrateRollbackEvent $event): void
    {
        $this->initProgressBar($event->getMigration());
    }

    /**
     * Reacts to rollback completion.
     *
     * @param MigrateRollbackEvent $event
     *   The map event.
     */
    public function onPostRollback(MigrateRollbackEvent $event): void
    {
        \Drupal::keyValue('migrate_last_imported')->delete($event->getMigration()->id());
        \Drupal::keyValue('migrate_status')->delete($event->getMigration()->id());
        $this->progressFinish();
        $this->rollbackFeedbackMessage();
        // This rollback may be called from an import, invoked with the
        // `--delete` option. In this case we let the ::onPostImport() to
        // unregister the listeners.
        // @see \Drush\Drupal\Migrate\MigrateExecutable::onPostImport()
        if (!$this->deleteMissingSourceRows) {
            $this->unregisterListeners();
        }
    }

    /**
     * Emits information on the rollback execution  progress.
     */
    protected function rollbackFeedbackMessage(bool $done = true): void
    {
        $rolledBack = $this->getRollbackCount();
        if ($done) {
            $singularMessage = "Rolled back 1 item - done with '@name'";
            $pluralMessage = "Rolled back @numitems items - done with '@name'";
        } else {
            $singularMessage = "Rolled back 1 item - continuing with '@name'";
            $pluralMessage = "Rolled back @numitems items - continuing with '@name'";
        }
        $this->message->display(
            \Drupal::translation()->formatPlural(
                $rolledBack,
                $singularMessage,
                $pluralMessage,
                [
                    '@numitems' => $rolledBack,
                    '@name' => $this->migration->id()
                ]
            )
        );
    }

    /**
     * Reacts to an item about to be imported.
     *
     * @param MigratePreRowSaveEvent $event
     *   The pre-save event.
     */
    public function onPreRowSave(MigratePreRowSaveEvent $event): void
    {
        $idMap = $event->getRow()->getIdMap();
        if (!empty($idMap['destid1'])) {
            $this->preExistingItem = true;
        } else {
            $this->preExistingItem = false;
        }
    }

    /**
     * Reacts aftre a row has been deleted.
     *
     * @param MigratePostRowSaveEvent $event
     *   The event.
     */
    public function onPostRowSave(MigratePostRowSaveEvent $event): void
    {
        $this->updateProgressBar();
    }

    /**
     * Reacts to item rollback.
     *
     * @param MigrateRowDeleteEvent $event
     *   The post-save event.
     */
    public function onPostRowDelete(MigrateRowDeleteEvent $event): void
    {
        if ($this->feedback && ($this->deleteCounter) && $this->deleteCounter % $this->feedback == 0) {
            $this->rollbackFeedbackMessage(false);
            $this->resetCounters();
        }
    }

    /**
     * Reacts to a new row being prepared.
     *
     * @param MigratePrepareRowEvent $event
     *   The prepare-row event.
     *
     * @throws MigrateSkipRowException
     */
    public function onPrepareRow(MigratePrepareRowEvent $event): void
    {
        $row = $event->getRow();
        $sourceId = $row->getSourceIdValues();

        if ($this->idlist !== []) {
            $skip = true;
            foreach ($this->idlist as $id) {
                if (array_values($sourceId) == $id) {
                    $skip = false;
                    break;
                }
            }
            if ($skip) {
                throw new MigrateSkipRowException('', false);
            }
        }

        // Collect all Source ID values so that we can handle missing source
        // rows post import.
        $this->allSourceIdValues[] = $sourceId;

        if ($this->feedback && $this->counter && $this->counter % $this->feedback === 0) {
            $this->importFeedbackMessage(false);
            $this->resetCounters();
        }
        $this->counter++;
        if ($this->limit && $this->counter >= $this->limit) {
            $event->getMigration()->interruptMigration(MigrationInterface::RESULT_COMPLETED);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdMap(): MigrateIdMapFilter
    {
        if (!isset($this->idMap)) {
            $this->idMap = new MigrateIdMapFilter(parent::getIdMap(), $this->idlist);
        }
        return $this->idMap;
    }

    /**
     * Returns the number of items created.
     */
    public function getCreatedCount(): int
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED];
    }

    /**
     * Returns the number of items updated.
     */
    public function getUpdatedCount(): int
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE];
    }

    /**
     * Returns the number of items ignored.
     */
    public function getIgnoredCount(): int
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED];
    }

    /**
     * Returns the number of items that failed.
     */
    public function getFailedCount(): int
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
    }

    /**
     * Returns the total number of items processed.
     *
     * Note that STATUS_NEEDS_UPDATE is not counted, since this is typically set
     * on stubs created as side effects, not on the primary item being imported.
     */
    public function getProcessedCount(): int
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED] +
            $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE] +
            $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED] +
            $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
    }

    /**
     * Returns the number of items rolled back.
     */
    public function getRollbackCount(): int
    {
        return $this->deleteCounter;
    }

    /**
     * Resets all the per-status counters to 0.
     */
    protected function resetCounters(): void
    {
        foreach ($this->saveCounters as $status => $count) {
            $this->saveCounters[$status] = 0;
        }
        $this->deleteCounter = 0;
    }

    /**
     * Initializes the command progress bar if possible.
     *
     * @param MigrationInterface $migration
     *   The migration.
     */
    protected function initProgressBar(MigrationInterface $migration): void
    {
        if ($this->exposeProgressBar) {
            $source = clone $migration->getSourcePlugin();
            $this->progressBar = new ProgressBar($this->output, $source->count(), 0);
            if ('\\' !== \DIRECTORY_SEPARATOR || 'Hyper' === getenv('TERM_PROGRAM')) {
                $this->progressBar->setEmptyBarCharacter('░');
                $this->progressBar->setProgressCharacter('');
                $this->progressBar->setBarCharacter('▓');
            }
        }
    }

    /**
     * Advances the progress bar.
     */
    public function updateProgressBar(): void
    {
        if ($this->exposeProgressBar) {
            $this->progressBar->advance();
        }
    }

    /**
     * Removes the progress bar after operation is finished.
     */
    public function progressFinish(): void
    {
        if ($this->exposeProgressBar) {
            $this->progressBar->finish();
            $this->output->write(PHP_EOL);
            $this->progressBar = null;
        }
    }

    /**
     * Unregisters all event listeners.
     */
    public function unregisterListeners(): void
    {
        $eventDispatcher = $this->getEventDispatcher();
        assert($eventDispatcher instanceof EventDispatcherInterface);
        foreach ($this->listeners as $event => $listener) {
            $eventDispatcher->removeListener($event, $listener);
        }
    }
}
