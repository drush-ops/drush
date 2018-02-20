<?php

namespace Drush\Drupal\Migrate;

use Drupal\Component\Utility\Timer;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateExecutable as MigrateExecutableBase;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drush\Drupal\Migrate\MigrateEvents as MigrateRunnerEvents;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateImportEvent;

class MigrateExecutable extends MigrateExecutableBase
{

    /**
     * Counters of map statuses.
     *
     * @var array
     *   Set of counters, keyed by MigrateIdMapInterface::STATUS_* constant.
     */
    protected $saveCounters = [
        MigrateIdMapInterface::STATUS_FAILED => 0,
        MigrateIdMapInterface::STATUS_IGNORED => 0,
        MigrateIdMapInterface::STATUS_IMPORTED => 0,
        MigrateIdMapInterface::STATUS_NEEDS_UPDATE => 0,
    ];

    /**
     * Counter of map deletions.
     *
     * @var int
     */
    protected $deleteCounter = 0;

    /**
     * Maximum number of items to process in this migration. 0 indicates no limit
     * is to be applied.
     *
     * @var int
     */
    protected $itemLimit = 0;

    /**
     * Frequency (in items) at which progress messages should be emitted.
     *
     * @var int
     */
    protected $feedback = 0;

    /**
     * Show timestamp in progress message.
     *
     * @var bool
     */
    protected $showTimestamp = false;

    /**
     * Show internal counter in progress message.
     *
     * @var bool
     */
    protected $showTotal = false;

    /**
     * List of specific source IDs to import.
     *
     * @var array
     */
    protected $idlist = [];

    /**
     * Count of number of items processed so far in this migration.
     * @var int
     */
    protected $counter = 0;

    /**
     * Whether the destination item exists before saving.
     *
     * @var bool
     */
    protected $preExistingItem = false;

    /**
     * List of event listeners we have registered.
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, array $options = [])
    {
        parent::__construct($migration, $message);
        Timer::start('migrate:' . $migration->getPluginId());
        if (isset($options['limit'])) {
            $this->itemLimit = $options['limit'];
        }
        if (isset($options['feedback'])) {
            $this->feedback = $options['feedback'];
        }
        if (isset($options['timestamp'])) {
            $this->showTimestamp = true;
        }
        if (isset($options['total'])) {
            $this->showTotal = true;
        }
        if (isset($options['idlist'])) {
            $this->idlist = explode(',', $options['idlist']);
            array_walk($this->idlist, function (&$value, $key) {
                $value = explode(':', $value);
            });
        }

        $this->listeners[MigrateEvents::MAP_SAVE] = [$this, 'onMapSave'];
        $this->listeners[MigrateEvents::MAP_DELETE] = [$this, 'onMapDelete'];
        $this->listeners[MigrateEvents::POST_IMPORT] = [$this, 'onPostImport'];
        $this->listeners[MigrateEvents::POST_ROLLBACK] = [$this, 'onPostRollback'];
        $this->listeners[MigrateEvents::PRE_ROW_SAVE] = [$this, 'onPreRowSave'];
        $this->listeners[MigrateEvents::POST_ROW_DELETE] = [$this, 'onPostRowDelete'];
        $this->listeners[MigrateRunnerEvents::PREPARE_ROW] = [$this, 'onPrepareRow'];
        foreach ($this->listeners as $event => $listener) {
            \Drupal::service('event_dispatcher')->addListener($event, $listener);
        }
    }

    /**
     * Counts up any map save events.
     *
     * @param \Drupal\migrate\Event\MigrateMapSaveEvent $event
     *   The map event.
     */
    public function onMapSave(MigrateMapSaveEvent $event)
    {
        // Only count saves for this migration.
        if ($event->getMap()->getQualifiedMapTableName() == $this->migration->getIdMap()->getQualifiedMapTableName()) {
            $fields = $event->getFields();
            // Distinguish between creation and update.
            if ($fields['source_row_status'] == MigrateIdMapInterface::STATUS_IMPORTED &&
                $this->preExistingItem
            ) {
                $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE]++;
            } else {
                $this->saveCounters[$fields['source_row_status']]++;
            }
        }
    }

    /**
     * Counts up any rollback events.
     *
     * @param \Drupal\migrate\Event\MigrateMapDeleteEvent $event
     *   The map event.
     */
    public function onMapDelete(MigrateMapDeleteEvent $event)
    {
        $this->deleteCounter++;
    }

    /**
     * Returns the number of items created.
     *
     * @return int
     */
    public function getCreatedCount()
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED];
    }

    /**
     * Returns the number of items updated.
     *
     * @return int
     */
    public function getUpdatedCount()
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE];
    }

    /**
     * Returns the number of items ignored.
     *
     * @return int
     */
    public function getIgnoredCount()
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED];
    }

    /**
     * Returns the number of items that failed.
     *
     * @return int
     */
    public function getFailedCount()
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
    }

    /**
     * Returns the total number of items processed. Note that STATUS_NEEDS_UPDATE
     * is not counted, since this is typically set on stubs created as side
     * effects, not on the primary item being imported.
     *
     * @return int
     */
    public function getProcessedCount()
    {
        return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED] +
            $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE] +
            $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED] +
            $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
    }

    /**
     * Returns the number of items rolled back.
     *
     * @return int
     */
    public function getRollbackCount()
    {
        return $this->deleteCounter;
    }

    /**
     * Resets all the per-status counters to 0.
     */
    protected function resetCounters()
    {
        foreach ($this->saveCounters as $status => $count) {
            $this->saveCounters[$status] = 0;
        }
        $this->deleteCounter = 0;
    }

    /**
     * Reacts to migration completion.
     *
     * @param \Drupal\migrate\Event\MigrateImportEvent $event
     *   The map event.
     */
    public function onPostImport(MigrateImportEvent $event)
    {
        $migrate_last_imported_store = \Drupal::keyValue('migrate_last_imported');
        $migrate_last_imported_store->set($event->getMigration()->id(), round(microtime(true) * 1000));
        $this->progressMessage();
        $this->removeListeners();
    }

    /**
     * Cleans up all our event listeners.
     */
    protected function removeListeners()
    {
        foreach ($this->listeners as $event => $listener) {
            \Drupal::service('event_dispatcher')->removeListener($event, $listener);
        }
    }

    /**
     * Emits information on what we've done since the last feedback (or the
     * beginning of this migration).
     *
     * @param bool $done
     */
    protected function progressMessage($done = true)
    {
        $processed = $this->getProcessedCount();
        $timer = Timer::read('migrate:' . $this->migration->getPluginId());
        $perminute = round(60 * ($processed / ($timer / 1000)), 1);
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
        $singular_message = "Processed 1 item $message";
        $plural_message = "Processed @numitems items $message";
        $this->message->display(
            \Drupal::translation()->formatPlural(
                $processed,
                $singular_message,
                $plural_message,
                [
                    '@time' => \Drupal::service('date.formatter')->format(time(), 'custom', 'r'),
                    '@numitems' => $processed,
                    '@created' => $this->getCreatedCount(),
                    '@updated' => $this->getUpdatedCount(),
                    '@failures' => $this->getFailedCount(),
                    '@ignored' => $this->getIgnoredCount(),
                    '@total' => $this->counter,
                    '@second' => round($timer / 1000, 1),
                    '@perminute' => $perminute,
                    '@name' => $this->migration->id(),
                ]
            )
        );
        Timer::start('migrate:' . $this->migration->getPluginId());
    }

    /**
     * Reacts to rollback completion.
     *
     * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
     *   The map event.
     */
    public function onPostRollback(MigrateRollbackEvent $event)
    {
        $this->rollbackMessage();
        $this->removeListeners();
    }

    /**
     * Emits information on what we've done since the last feedback (or the
     * beginning of this migration).
     *
     * @param bool $done
     */
    protected function rollbackMessage($done = true)
    {
        $rolled_back = $this->getRollbackCount();
        if ($done) {
            $singular_message = "Rolled back 1 item - done with '@name'";
            $plural_message = "Rolled back @numitems items - done with '@name'";
        } else {
            $singular_message = "Rolled back 1 item - continuing with '@name'";
            $plural_message = "Rolled back @numitems items - continuing with '@name'";
        }
        $this->message->display(
            \Drupal::translation()->formatPlural(
                $rolled_back,
                $singular_message,
                $plural_message,
                [
                    '@numitems' => $rolled_back,
                    '@name' => $this->migration->id()
                ]
            )
        );
    }

    /**
     * Reacts to an item about to be imported.
     *
     * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
     *   The pre-save event.
     */
    public function onPreRowSave(MigratePreRowSaveEvent $event)
    {
        $id_map = $event->getRow()->getIdMap();
        if (!empty($id_map['destid1'])) {
            $this->preExistingItem = true;
        } else {
            $this->preExistingItem = false;
        }
    }

    /**
     * Reacts to item rollback.
     *
     * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
     *   The post-save event.
     */
    public function onPostRowDelete(MigrateRowDeleteEvent $event)
    {
        if ($this->feedback && ($this->deleteCounter) && $this->deleteCounter % $this->feedback == 0) {
            $this->rollbackMessage(false);
            $this->resetCounters();
        }
    }

    /**
     * Reacts to a new row.
     *
     * @param \Drush\Drupal\Migrate\MigratePrepareRowEvent $event
     *   The prepare-row event.
     *
     * @throws \Drupal\migrate\MigrateSkipRowException
     *
     */
    public function onPrepareRow(MigratePrepareRowEvent $event)
    {
        if (!empty($this->idlist)) {
            $row = $event->getRow();
            /**
             * @TODO replace for $source_id = $row->getSourceIdValues(); when https://www.drupal.org/node/2698023 is fixed
             */
            $migration = $event->getMigration();
            $source_id = array_merge(array_flip(array_keys($migration->getSourcePlugin()
                ->getIds())), $row->getSourceIdValues());
            $skip = true;
            foreach ($this->idlist as $item) {
                if (array_values($source_id) == $item) {
                    $skip = false;
                    break;
                }
            }
            if ($skip) {
                throw new MigrateSkipRowException(null, false);
            }
        }
        if ($this->feedback && ($this->counter) && $this->counter % $this->feedback == 0) {
            $this->progressMessage(false);
            $this->resetCounters();
        }
        $this->counter++;
        if ($this->itemLimit && ($this->counter) >= $this->itemLimit) {
            $event->getMigration()->interruptMigration(MigrationInterface::RESULT_COMPLETED);
        }
    }
}
