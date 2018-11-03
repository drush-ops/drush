<?php

namespace Drupal\block;

interface BlockRepositoryInterface {

  /**
   * Return only visible regions.
   *
   * @see system_region_list()
   */
  const REGIONS_VISIBLE = 'visible';

  /**
   * Return all regions.
   *
   * @see system_region_list()
   */
  const REGIONS_ALL = 'all';

  /**
   * Returns an array of regions and their block entities.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata[] $cacheable_metadata
   *   (optional) List of CacheableMetadata objects, keyed by region. This is
   *   by reference and is used to pass this information back to the caller.
   *
   * @return array
   *   The array is first keyed by region machine name, with the values
   *   containing an array keyed by block ID, with block entities as the values.
   */
  public function getVisibleBlocksPerRegion(array &$cacheable_metadata = []);

}
