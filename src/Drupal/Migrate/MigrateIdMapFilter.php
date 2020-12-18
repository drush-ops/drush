<?php

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrateIdMapInterface;

/**
 * Filters the ID map by an ID list.
 */
class MigrateIdMapFilter extends \FilterIterator
{

    /**
     * List of specific IDs to filter on.
     *
     * @var array
     */
    protected $idList;

    /**
     * @param \Drupal\migrate\Plugin\MigrateIdMapInterface $id_map
     *   The ID map.
     * @param array $id_list
     *   The id list to use in the filter.
     */
    public function __construct(MigrateIdMapInterface $id_map, array $id_list)
    {
        parent::__construct($id_map);
        $this->idList = $id_list;
    }

    /**
     * {@inheritdoc}
     */
    public function accept(): bool
    {
        return !$this->idList || in_array(array_values($this->getInnerIterator()->currentSource()), $this->idList);
    }
}
