<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrateIdMapInterface;

/**
 * Filters the ID map by a source and/or a destination ID list.
 */
class MigrateIdMapFilter extends \FilterIterator
{
    /**
     * List of specific source IDs to filter on.
     */
    protected array $sourceIdList;

    /**
     * List of specific destination IDs to filter on.
     */
    protected array $destinationIdList;

    /**
     * @param MigrateIdMapInterface $idMap
     *   The ID map.
     * @param array $sourceIdList
     *   The source ID list to filter on.
     * @param array $destinationIdList
     *   The destination ID list to filter on.
     */
    public function __construct(MigrateIdMapInterface $idMap, array $sourceIdList = [], array $destinationIdList = [])
    {
        parent::__construct($idMap);
        $this->sourceIdList = array_map('array_values', $sourceIdList);
        $this->destinationIdList = array_map('array_values', $destinationIdList);
    }

    /**
     * {@inheritdoc}
     */
    public function accept(): bool
    {
        if (!$this->sourceIdList && !$this->destinationIdList) {
            // No filtering has been requested.
            return true;
        }

        /** @var MigrateIdMapInterface $idMap */
        $idMap = $this->getInnerIterator();

        $acceptedBySourceIdList = $this->sourceIdList && in_array(array_values($idMap->currentSource()), $this->sourceIdList);
        // Either no destination filtering has been requested, or a source
        // filtering was requested but is not satisfied.
        if (!$this->destinationIdList || ($this->sourceIdList && !$acceptedBySourceIdList)) {
            return $acceptedBySourceIdList;
        }

        return in_array(array_values($idMap->currentDestination()), $this->destinationIdList);
    }
}
